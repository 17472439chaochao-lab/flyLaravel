<?php

declare(strict_types=1);

namespace Readfly\Laravel\Modules;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Readfly\Laravel\Support\Logger;
use RuntimeException;
use Throwable;

class RepositoryModule
{
    protected mixed $default_model;

    protected mixed $model;

    protected mixed $connection;

    protected string $table_name;

    protected bool $debug = false;

    protected bool $auto_timestamp = true;

    public function __construct(mixed $model, mixed $connection = null)
    {
        $class = $model::class;
        $this->default_model = $model;
        $this->table_name = defined($class.'::TABLE_NAME') ? (string) constant($class.'::TABLE_NAME') : $model->getTable();
        $this->auto_timestamp = defined($class.'::AUTO_TIMESTAMP')
            ? (bool) constant($class.'::AUTO_TIMESTAMP')
            : $model->usesTimestamps();
        $this->setConnection($connection ?? $model->getConnectionName());
    }

    public function setConnection(mixed $connection = null): static
    {
        $this->connection = $connection;
        $class = $this->default_model::class;
        $model = new $class();

        if ($connection !== null) {
            $model->setConnection($connection);
        }

        $this->model = $model;

        return $this;
    }

    public function model(): Model
    {
        $class = $this->default_model::class;
        $model = new $class();

        if ($this->connection !== null) {
            $model->setConnection($this->connection);
        }

        $this->model = $model;

        return $this->model;
    }

    public function setDebug(bool $debug = true): static
    {
        $this->debug = $debug;

        return $this;
    }

    public function tableName(): string
    {
        return $this->table_name;
    }

    public function sqlStatement(string $sql, mixed $connection = null): bool
    {
        return DB::connection($connection ?? $this->connection)->statement($sql);
    }

    public function transaction(callable $callback): mixed
    {
        return $this->model()->getConnection()->transaction($callback);
    }

    public function stripslashesValue(mixed $value): mixed
    {
        return is_string($value) ? stripslashes($value) : $value;
    }

    public function lists(array $conditions = [], mixed $fields = [], array $joins = [], array $orders = [], string $group = '', int $page = 1, int $page_size = 0): array
    {
        $page = max(1, $page);
        $page_size = $this->normalizePageSize($page_size);
        $total_count = $this->count([], $joins, $group);
        $matched_count = $this->count($conditions, $joins, $group);
        $lists = $this->limit($conditions, $fields, $joins, $orders, $group, $page, $page_size);

        return [
            'total_count' => $total_count,
            'matched_count' => $matched_count,
            'lists' => $lists,
            'total_pages' => $page_size > 0 ? (int) ceil($matched_count / $page_size) : 0,
            'page' => $page,
            'page_size' => $page_size,
        ];
    }

    public function limit(array $conditions = [], mixed $fields = [], array $joins = [], array $orders = [], string $group = '', int $page = 1, int $page_size = 0): mixed
    {
        $page = max(1, $page);
        $page_size = $this->normalizePageSize($page_size);
        $query = $this->buildQuery($conditions, $joins, $orders, $group);

        [$query, $fields] = $this->applyFields($query, $fields);
        $this->debug && $query->dd();

        return $this->normalizeResult(
            $query->offset(($page - 1) * $page_size)->limit($page_size)->get($fields)
        );
    }

    public function row(array $conditions = [], mixed $fields = [], array $orders = []): mixed
    {
        $query = $this->buildQuery($conditions, [], $orders);
        [$query, $fields] = $this->applyFields($query, $fields, true);
        $this->debug && $query->dd();

        return $this->normalizeResult($query->first($fields));
    }

    public function find(array $conditions, string $field, array $orders = []): mixed
    {
        $query = $this->buildQuery($conditions, [], $orders);
        $row = $query->first([$field]);

        return $row ? data_get($row, $field, false) : false;
    }

    public function pluck(string $field, array $conditions = []): mixed
    {
        $query = $this->applyConditions($this->model()->newQuery(), $conditions);
        $this->debug && $query->dd();

        return $query->pluck($field)->toArray();
    }

    public function get(array $conditions = [], mixed $fields = [], array $joins = [], array $orders = [], string $group = ''): mixed
    {
        $query = $this->buildQuery($conditions, $joins, $orders, $group);
        [$query, $fields] = $this->applyFields($query, $fields);
        $this->debug && $query->dd();

        return $this->normalizeResult($query->get($fields));
    }

    public function insertGetId(array $data): mixed
    {
        return $this->model()->newQuery()->insertGetId($this->prepareData($data, true));
    }

    public function insertAll(array $data): mixed
    {
        if ($data === []) {
            return [];
        }

        try {
            return $this->transaction(function () use ($data): array {
                $ids = [];
                foreach ($data as $group) {
                    $ids[] = $this->insertGetId((array) $group);
                }

                return $ids;
            });
        } catch (Throwable $exception) {
            Logger::record('repository:insertAll:failed', ['exception' => $exception, 'data' => $data]);

            return false;
        }
    }

    public function update(array $conditions, array $data): mixed
    {
        $query = $this->applyConditions($this->model()->newQuery(), $conditions);

        return $query->update($this->prepareData($data, false));
    }

    public function delete(array $conditions): mixed
    {
        return $this->applyConditions($this->model()->newQuery(), $conditions)->delete();
    }

    public function count(array $conditions = [], array $joins = [], string $group = ''): int
    {
        $query = $this->applyConditions($this->model()->newQuery(), $conditions);
        $query = $this->applyJoins($query, $joins);
        $query = $this->applyGroup($query, $group);

        if ($group !== '') {
            return (int) $query->get()->count();
        }

        return (int) $query->count();
    }

    public function updateOrInsert(array $conditions, array $data): mixed
    {
        if ($this->exists($conditions)) {
            return $this->update($conditions, $data);
        }

        return $this->insertGetId(array_merge($conditions, $data));
    }

    public function increment(string $field, array $conditions = [], int $inc = 1): mixed
    {
        return $this->applyConditions($this->model()->newQuery(), $conditions)->increment($field, $inc);
    }

    public function decrement(string $field, array $conditions = [], int $dec = 1): mixed
    {
        return $this->applyConditions($this->model()->newQuery(), $conditions)->decrement($field, $dec);
    }

    public function max(string $field, array $conditions = []): mixed
    {
        return $this->applyConditions($this->model()->newQuery(), $conditions)->max($field);
    }

    public function min(string $field, array $conditions = []): mixed
    {
        return $this->applyConditions($this->model()->newQuery(), $conditions)->min($field);
    }

    public function avg(string $field, array $conditions = []): mixed
    {
        return $this->applyConditions($this->model()->newQuery(), $conditions)->avg($field);
    }

    public function sum(string $field, array $conditions = []): mixed
    {
        return $this->applyConditions($this->model()->newQuery(), $conditions)->sum($field);
    }

    public function exists(array $conditions = []): bool
    {
        return $this->applyConditions($this->model()->newQuery(), $conditions)->exists();
    }

    public function doesntExists(array $conditions = []): bool
    {
        return $this->applyConditions($this->model()->newQuery(), $conditions)->doesntExist();
    }

    public function setIncrementId(int $auto_increment = 1, mixed $connection = null): bool
    {
        $table = $this->wrapIdentifier($this->table_name);

        return $this->sqlStatement('ALTER TABLE '.$table.' AUTO_INCREMENT = '.(int) $auto_increment, $connection);
    }

    public function setTableComment(string $comment = '', mixed $connection = null): bool
    {
        $table = $this->wrapIdentifier($this->table_name);
        $comment = str_replace(['\\', "'"], ['\\\\', "''"], $comment);

        return $this->sqlStatement("ALTER TABLE {$table} COMMENT '{$comment}'", $connection);
    }

    public function uniqueCode(string $field, string $type = 'uuid', bool $upper = false, string $prefix = ''): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = $this->generateCode($type);

            if ($upper) {
                $code = strtoupper($code);
            }

            if ($prefix !== '') {
                $code = $prefix.$code;
            }

            if (! $this->exists([$field => $code])) {
                return $code;
            }
        }

        throw new RuntimeException('无法生成唯一编码：'.$field);
    }

    protected function generateCode(string $type): string
    {
        if ($type === 'md5') {
            return md5(Str::uuid()->toString().Str::random(12));
        }

        if ($type === 'sn') {
            return date('YmdHis').Str::upper(Str::random(8));
        }

        if ($type === 'uuid') {
            return (string) Str::uuid();
        }

        $segments = explode(':', $type);
        $rule = Arr::first($segments);
        $length = (int) Arr::last($segments);

        if ($length <= 0) {
            $length = 16;
        }

        if (in_array($rule, ['int', 'integral', 'number', 'num', 'digit'], true)) {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= (string) random_int(0, 9);
            }

            return $code;
        }

        $code = Str::random($length);

        return in_array($rule, ['l', 'lower'], true) ? strtolower($code) : $code;
    }

    protected function buildQuery(array $conditions = [], array $joins = [], array $orders = [], string $group = ''): EloquentBuilder
    {
        $query = $this->applyConditions($this->model()->newQuery(), $conditions);
        $query = $this->applyJoins($query, $joins);
        $query = $this->applyOrders($query, $orders);

        return $this->applyGroup($query, $group);
    }

    protected function applyConditions(EloquentBuilder $query, array $conditions): EloquentBuilder
    {
        foreach ($conditions as $field => $condition) {
            if (is_int($field)) {
                throw new InvalidArgumentException('查询条件必须使用字段名作为键');
            }

            [$rule, $value] = $this->parseCondition($condition);
            $boolean = 'and';

            if (str_starts_with($rule, 'or-')) {
                $boolean = 'or';
                $rule = substr($rule, 3);
            }

            $this->applyCondition($query, (string) $field, $rule, $value, $boolean);
        }

        return $query;
    }

    protected function parseCondition(mixed $condition): array
    {
        if (is_array($condition) && isset($condition[0]) && is_string($condition[0]) && $this->isRule($condition[0])) {
            return [strtolower($condition[0]), $condition[1] ?? null];
        }

        return ['=', $condition];
    }

    protected function isRule(string $rule): bool
    {
        return in_array(strtolower($rule), [
            '=', '!=', '<>', '>', '>=', '<', '<=',
            'like', 'not-like', 'in', 'not-in', 'between', 'not-between',
            'null', 'not-null', 'raw', 'select-raw', 'column',
            'date', 'month', 'day', 'year', 'time',
            'json', 'json-contains', 'json-length', 'json-length-gt',
            'json-length-egt', 'json-length-lt', 'json-length-elt', 'json-length-eq',
            'columns', 'or-like', 'or-in', 'or-not-in', 'or-between', 'or-not-between',
            'or-null', 'or-not-null', 'or-column', 'or-not-like', 'or-raw', 'or-not-exists',
        ], true);
    }

    protected function applyCondition(EloquentBuilder $query, string $field, string $rule, mixed $value, string $boolean): void
    {
        $method = $boolean === 'or' ? 'orWhere' : 'where';

        switch ($rule) {
            case 'raw':
                [$sql, $bindings] = $this->splitRaw($value);
                $query->whereRaw($sql, $bindings, $boolean);

                return;

            case 'select-raw':
                [$sql, $bindings] = $this->splitRaw($value);
                $query->selectRaw($sql, $bindings);

                return;

            case 'like':
            case 'not-like':
                $this->applyLike($query, $field, (string) $value, $rule === 'not-like', $boolean);

                return;

            case 'column':
                $this->applyColumn($query, $field, $value, $boolean);

                return;

            case 'columns':
                $query->whereColumn((array) $value);

                return;

            case 'in':
            case 'not-in':
            case 'between':
            case 'not-between':
                $suffix = match ($rule) {
                    'in' => 'In',
                    'not-in' => 'NotIn',
                    'between' => 'Between',
                    default => 'NotBetween',
                };
                $query->{$method.$suffix}($this->assertField($field), $value);

                return;

            case 'null':
            case 'not-null':
                $query->{$method.($rule === 'null' ? 'Null' : 'NotNull')}($this->assertField($field));

                return;

            case 'date':
            case 'month':
            case 'day':
            case 'year':
                $query->{$method.ucfirst($rule)}($this->assertField($field), $value);

                return;

            case 'time':
                $query->{$method.'Time'}($this->assertField($field), '=', $value);

                return;

            case 'json':
                $query->{$method}($this->assertField($field), $value);

                return;

            case 'json-contains':
                $query->{$method.'JsonContains'}($this->assertField($field), $value);

                return;

            case 'json-length':
            case 'json-length-gt':
            case 'json-length-egt':
            case 'json-length-lt':
            case 'json-length-elt':
            case 'json-length-eq':
                $operator = match ($rule) {
                    'json-length-gt' => '>',
                    'json-length-egt' => '>=',
                    'json-length-lt' => '<',
                    'json-length-elt' => '<=',
                    'json-length-eq' => '=',
                    default => null,
                };
                $callback = function (EloquentBuilder $inner) use ($field, $operator, $value): void {
                    $operator === null
                        ? $inner->whereJsonLength($this->assertField($field), (int) $value)
                        : $inner->whereJsonLength($this->assertField($field), $operator, (int) $value);
                };
                $boolean === 'or' ? $query->orWhere($callback) : $query->where($callback);

                return;

            default:
                $query->{$method}($this->assertField($field), $rule === '=' ? '=' : $rule, $value);
        }
    }

    protected function applyLike(EloquentBuilder $query, string $field, string $value, bool $not, string $boolean): void
    {
        $fields = array_filter(array_map('trim', explode('|', $field)));
        $operator = $not ? 'not like' : 'like';
        $needle = '%'.str_replace(' ', '', $value).'%';

        $callback = function (EloquentBuilder $inner) use ($fields, $operator, $needle): void {
            foreach ($fields as $index => $item) {
                $column = $this->wrapIdentifier($this->assertField($item));
                $sql = 'replace('.$column.", ' ', '') ".$operator.' ?';
                $inner->whereRaw($sql, [$needle], $index === 0 ? 'and' : 'or');
            }
        };

        $boolean === 'or' ? $query->orWhere($callback) : $query->where($callback);
    }

    protected function applyColumn(EloquentBuilder $query, string $field, mixed $value, string $boolean): void
    {
        $method = $boolean === 'or' ? 'orWhereColumn' : 'whereColumn';

        if (is_string($value)) {
            $query->{$method}($this->assertField($field), $value);

            return;
        }

        $query->{$method}($this->assertField($field), Arr::first($value), Arr::last($value));
    }

    protected function applyJoins(EloquentBuilder $query, array $joins): EloquentBuilder
    {
        foreach ($joins as $join) {
            $rule = strtolower((string) ($join[0] ?? 'inner'));
            $table = (string) ($join[1] ?? '');
            $first = (string) ($join[2] ?? '');
            $operator = (string) ($join[3] ?? '=');
            $second = (string) ($join[4] ?? '');

            if ($table === '' || $first === '' || $second === '') {
                throw new InvalidArgumentException('表连接规则不完整');
            }

            $callback = static fn ($builder) => $builder->on($first, $operator, $second);

            match ($rule) {
                'left' => $query->leftJoin($table, $callback),
                'right' => $query->rightJoin($table, $callback),
                default => $query->join($table, $callback),
            };
        }

        return $query;
    }

    protected function applyOrders(EloquentBuilder $query, array $orders): EloquentBuilder
    {
        if ($orders === []) {
            return $query->latest();
        }

        foreach ($orders as $field => $order) {
            if (is_array($order)) {
                $rule = Arr::first($order);
                $raw = Arr::last($order);

                match ($rule) {
                    ModelModule::RANDOM_ORDER_BY => $query->inRandomOrder(),
                    ModelModule::RAW_ORDER_BY => $query->orderByRaw((string) $raw),
                    ModelModule::FIELD_ORDER_BY => $this->applyFieldOrder($query, (string) $field, $raw),
                    default => null,
                };

                continue;
            }

            $field = $this->assertField((string) $field);
            $direction = in_array(strtolower((string) $order), ['asc', 'desc'], true) ? strtolower((string) $order) : 'asc';
            $query->orderBy($field, $direction);
        }

        return $query;
    }

    protected function applyFieldOrder(EloquentBuilder $query, string $field, mixed $values): void
    {
        $values = is_array($values) ? array_values($values) : array_values(array_filter(array_map('trim', explode(',', (string) $values))));
        $wrapped = $this->wrapIdentifier($this->assertField($field));

        if ($values === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $query->orderByRaw('FIELD('.$wrapped.', '.$placeholders.')', $values);
    }

    protected function applyGroup(EloquentBuilder $query, string $group): EloquentBuilder
    {
        if ($group !== '') {
            $query->groupByRaw($group);
        }

        return $query;
    }

    protected function applyFields(EloquentBuilder $query, mixed $fields = [], bool $is_raw = false): array
    {
        if (is_array($fields)) {
            return [$query, $fields !== [] ? $fields : ['*']];
        }

        if (is_string($fields) && $fields !== '') {
            [$sql, $bindings] = $this->splitRaw($fields);

            return [$query->selectRaw($sql, $bindings), $is_raw ? ['*'] : []];
        }

        return [$query, ['*']];
    }

    protected function prepareData(array $data, bool $insert): array
    {
        if ($this->auto_timestamp) {
            $now = date('Y-m-d H:i:s');
            $data = $insert
                ? array_merge(['created_at' => $now, 'updated_at' => $now], $data)
                : array_merge(['updated_at' => $now], $data);
        }

        foreach ($data as $field => $value) {
            if (is_array($value) || $value instanceof \JsonSerializable) {
                $data[$field] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif ($value instanceof \DateTimeInterface) {
                $data[$field] = $value->format('Y-m-d H:i:s');
            } elseif (is_bool($value)) {
                $data[$field] = (int) $value;
            }
        }

        return $data;
    }

    protected function normalizeResult(mixed $result): mixed
    {
        if ($result instanceof Collection) {
            return $result->toArray();
        }

        if ($result instanceof Model) {
            return $result->toArray();
        }

        if (is_array($result)) {
            return array_map(fn (mixed $item): mixed => $this->normalizeResult($item), $result);
        }

        return $result;
    }

    protected function normalizePageSize(int $page_size): int
    {
        $default = (int) $this->configValue('toolkit.repository.page_size', 20);
        $max = (int) $this->configValue('toolkit.repository.max_page_size', 200);

        if ($page_size <= 0) {
            return $default > 0 ? min($default, $max > 0 ? $max : $default) : 20;
        }

        return $max > 0 ? min($page_size, $max) : $page_size;
    }

    protected function assertField(string $field): string
    {
        foreach (explode('|', $field) as $item) {
            if (! preg_match('/^[A-Za-z0-9_.*]+$/', trim($item))) {
                throw new InvalidArgumentException('非法的字段名：'.$item);
            }
        }

        return $field;
    }

    protected function splitRaw(mixed $value): array
    {
        if (is_array($value)) {
            return [(string) Arr::first($value), (array) (Arr::last($value) ?? [])];
        }

        return [(string) $value, []];
    }

    protected function wrapIdentifier(string $identifier): string
    {
        return $this->model()->newQuery()->getQuery()->getGrammar()->wrap($identifier);
    }

    protected function configValue(string $key, mixed $default = null): mixed
    {
        return function_exists('config') ? config($key, $default) : $default;
    }
}