<?php

declare(strict_types=1);

namespace Readfly\Laravel\Console;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class FieldDefinition
{
    private const TYPE_ALIASES = [
        'int' => 'integer',
        'integer' => 'integer',
        'bigint' => 'bigInteger',
        'smallint' => 'smallInteger',
        'tinyint' => 'tinyInteger',
        'mediumint' => 'mediumInteger',
        'bool' => 'boolean',
        'boolean' => 'boolean',
        'varchar' => 'string',
        'string' => 'string',
        'char' => 'char',
        'text' => 'text',
        'mediumtext' => 'mediumText',
        'longtext' => 'longText',
        'decimal' => 'decimal',
        'float' => 'float',
        'double' => 'double',
        'json' => 'json',
        'jsonb' => 'jsonb',
        'date' => 'date',
        'datetime' => 'dateTime',
        'timestamp' => 'timestamp',
        'time' => 'time',
        'year' => 'year',
        'uuid' => 'uuid',
        'enum' => 'enum',
        'binary' => 'binary',
        'email' => 'string',
        'url' => 'string',
    ];

    public function __construct(
        public string $name,
        public string $type = 'string',
        public string $comment = '',
        public mixed $default = null,
        public bool $nullable = false,
        public bool $unsigned = false,
        public array $customRules = [],
    ) {
    }

    public static function parse(string $definition): self
    {
        $segments = self::splitDelimited(trim($definition), ':');
        $name = self::unquote($segments[0] ?? '');

        if ($name === '' || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException('非法字段名：'.$definition);
        }

        $type = self::unquote($segments[1] ?? 'string');
        $commentSource = $segments[2] ?? Str::headline($name);
        $default = isset($segments[3]) ? self::unquote($segments[3]) : null;
        $nullable = isset($segments[4]) ? self::toBool(self::unquote($segments[4])) : false;
        $customRules = isset($segments[5]) ? self::normalizeRules(self::unquote($segments[5])) : [];

        if ($default === null && str_contains($commentSource, '=')) {
            $parts = self::splitDelimited($commentSource, '=');

            if (count($parts) > 1) {
                $commentSource = array_shift($parts);
                $default = implode('=', $parts);
            }
        }

        $comment = self::unquote((string) $commentSource);

        if ($default !== null) {
            $default = self::unquote($default);
        }

        if (str_starts_with($type, '?')) {
            $nullable = true;
            $type = substr($type, 1);
        }

        if (str_ends_with($type, '!')) {
            $unsigned = true;
            $type = rtrim($type, '!');
        } else {
            $unsigned = false;
        }

        if ($default === 'null' || $default === 'NULL' || $default === '') {
            $default = null;
        }

        if ($type === '' || ! preg_match('/^[A-Za-z][A-Za-z0-9_]*(?:\(.*\))?$/', $type)) {
            throw new InvalidArgumentException('非法字段类型：'.$definition);
        }

        $baseType = (string) preg_replace('/\(.*\)$/', '', $type);
        $type = strtolower($baseType).substr($type, strlen($baseType));

        return new self($name, $type, $comment, $default, $nullable, $unsigned, $customRules);
    }

    public static function fromArray(array $data): self
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '' || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException('非法字段名：'.$name);
        }

        $type = trim((string) ($data['type'] ?? 'string'));
        $comment = (string) ($data['comment'] ?? Str::headline($name));
        $default = $data['default'] ?? null;
        $nullable = (bool) ($data['nullable'] ?? false);
        $unsigned = (bool) ($data['unsigned'] ?? false);

        if (str_starts_with($type, '?')) {
            $nullable = true;
            $type = substr($type, 1);
        }

        if (str_ends_with($type, '!')) {
            $unsigned = true;
            $type = rtrim($type, '!');
        }

        if ($type === '' || ! preg_match('/^[A-Za-z][A-Za-z0-9_]*(?:\(.*\))?$/', $type)) {
            throw new InvalidArgumentException('非法字段类型：'.$name);
        }

        $baseType = (string) preg_replace('/\(.*\)$/', '', $type);
        $type = strtolower($baseType).substr($type, strlen($baseType));
        $rules = $data['rules'] ?? [];

        if (is_string($rules)) {
            $rules = self::normalizeRules($rules);
        }

        if (! is_array($rules)) {
            throw new InvalidArgumentException('字段规则必须是数组：'.$name);
        }

        $customRules = array_values(array_filter(array_map(static fn ($rule): string => trim((string) $rule), $rules)));

        return new self($name, $type, $comment, $default, $nullable, $unsigned, $customRules);
    }

    public static function parseJson(string $json): array
    {
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new InvalidArgumentException('字段 JSON 解析失败');
        }

        $items = array_is_list($data) ? $data : [];

        if (! array_is_list($data)) {
            foreach ($data as $name => $definition) {
                if (is_array($definition)) {
                    $definition['name'] = $definition['name'] ?? $name;
                    $items[] = $definition;
                }
            }
        }

        $fields = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $field = self::fromArray($item);

            if (isset($fields[$field->name])) {
                throw new InvalidArgumentException('字段重复定义：'.$field->name);
            }

            $fields[$field->name] = $field;
        }

        return $fields;
    }

    public static function parseList(string $definitions): array
    {
        if (trim($definitions) === '') {
            return [];
        }

        $items = self::splitFieldList($definitions);
        $fields = [];

        foreach ($items as $item) {
            $item = trim($item);

            if ($item === '') {
                continue;
            }

            $field = self::parse($item);

            if (isset($fields[$field->name])) {
                throw new InvalidArgumentException('字段重复定义：'.$field->name);
            }

            $fields[$field->name] = $field;
        }

        return $fields;
    }

    public function baseType(): string
    {
        return (string) preg_replace('/\(.*\)$/', '', $this->type);
    }

    public function method(): string
    {
        $base = $this->baseType();
        $method = self::TYPE_ALIASES[$base] ?? null;

        if ($method === null || ! method_exists(Blueprint::class, $method)) {
            throw new InvalidArgumentException('不支持的字段类型：'.$this->type);
        }

        return $method;
    }

    public function arguments(): array
    {
        $arguments = [var_export($this->name, true)];

        foreach ($this->typeArguments() as $argument) {
            if ($this->baseType() === 'enum') {
                $arguments[] = var_export(trim($argument, " \t\n\r\0\x0B'\""), true);

                continue;
            }

            $arguments[] = preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/', $argument) === 1
                ? $argument
                : var_export($argument, true);
        }

        return $arguments;
    }

    public function migrationLine(): string
    {
        $arguments = $this->arguments();

        if ($this->baseType() === 'enum') {
            $values = array_slice($arguments, 1);

            if ($values === []) {
                throw new InvalidArgumentException('enum 字段至少需要一个枚举值：'.$this->name);
            }

            $arguments = [$arguments[0], '['.implode(', ', $values).']'];
        }

        $line = '$table->'.$this->method().'('.implode(', ', $arguments).')';

        if ($this->unsigned) {
            $line .= '->unsigned()';
        }

        if ($this->nullable) {
            $line .= '->nullable()';
        }

        if ($this->default !== null) {
            $line .= '->default('.$this->export($this->default).')';
        }

        return $line.'->comment('.var_export($this->comment, true).');';
    }

    public function cast(): ?string
    {
        return match ($this->baseType()) {
            'json', 'jsonb', 'array' => "'array'",
            'bool', 'boolean' => "'boolean'",
            'int', 'integer', 'bigint', 'smallint', 'tinyint', 'mediumint' => "'integer'",
            'decimal' => $this->decimalCast(),
            'float', 'double' => "'float'",
            'date' => "'date'",
            'datetime', 'timestamp' => "'datetime'",
            default => null,
        };
    }

    public function rules(): array
    {
        $base = $this->baseType();
        $max = 255;

        if (preg_match('/\((\d+)/', $this->type, $matches) === 1) {
            $max = (int) $matches[1];
        }

        $rules = match ($base) {
            'int', 'integer', 'bigint', 'smallint', 'tinyint', 'mediumint' => ['integer'],
            'decimal', 'float', 'double' => ['numeric'],
            'bool', 'boolean' => ['boolean'],
            'json', 'jsonb' => ['array'],
            'date' => ['date'],
            'datetime', 'timestamp' => ['date'],
            'time' => ['date_format:H:i:s'],
            'year' => ['digits:4'],
            'uuid' => ['uuid'],
            'email' => ['email', 'max:'.$max],
            'url' => ['url', 'max:'.$max],
            'enum' => ['string', 'Rule::in(['.implode(', ', array_map(static fn (string $value): string => var_export($value, true), $this->enumValues())).'])' ],
            'text', 'mediumtext', 'longtext' => ['string'],
            default => ['string', 'max:'.$max],
        };

        $presenceRules = array_values(array_intersect($this->customRules, ['required', 'nullable']));

        if ($presenceRules === []) {
            $presenceRules = [$this->nullable ? 'nullable' : 'required'];
        }

        $rules = array_merge($presenceRules, $rules, $this->customRules);

        return array_values(array_unique($rules));
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'comment' => $this->comment,
            'default' => $this->default,
            'nullable' => $this->nullable,
            'unsigned' => $this->unsigned,
            'rules' => $this->customRules,
        ];
    }

    private function typeArguments(): array
    {
        if (preg_match('/\((.*)\)$/', $this->type, $matches) !== 1) {
            return [];
        }

        return array_map([self::class, 'unquote'], self::splitDelimited($matches[1], ','));
    }

    private static function normalizeRules(string $rules): array
    {
        return array_values(array_filter(array_map('trim', explode('|', $rules)), static fn (string $rule): bool => $rule !== ''));
    }

    private static function splitFieldList(string $value): array
    {
        $parts = [];
        $current = '';
        $quote = null;
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $character = $value[$index];

            if ($quote !== null) {
                if ($character === '\\' && $index + 1 < $length) {
                    $current .= $character.$value[++$index];

                    continue;
                }

                $current .= $character;

                if ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
                $current .= $character;

                continue;
            }

            if ($character === ';' || $character === '|' || $character === "\n" || $character === "\r") {
                $parts[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $character;
        }

        $parts[] = trim($current);

        return $parts;
    }

    private static function splitDelimited(string $value, string $delimiter): array
    {
        $parts = [];
        $current = '';
        $quote = null;
        $depth = 0;
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $character = $value[$index];

            if ($quote !== null) {
                if ($character === '\\' && $index + 1 < $length) {
                    $current .= $character.$value[++$index];

                    continue;
                }

                $current .= $character;

                if ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
                $current .= $character;

                continue;
            }

            if ($character === '(') {
                $depth++;
                $current .= $character;

                continue;
            }

            if ($character === ')') {
                $depth = max(0, $depth - 1);
                $current .= $character;

                continue;
            }

            if ($character === '\\' && $index + 1 < $length && $value[$index + 1] === $delimiter) {
                $current .= $delimiter;
                $index++;

                continue;
            }

            if ($character === $delimiter && $depth === 0) {
                $parts[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $character;
        }

        $parts[] = trim($current);

        return $parts;
    }

    private static function unquote(string $value): string
    {
        $value = trim($value);
        $length = strlen($value);

        if ($length >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[$length - 1] === $value[0]) {
            return stripcslashes(substr($value, 1, -1));
        }

        return $value;
    }

    private function enumValues(): array
    {
        $values = array_map(
            static fn (string $value): string => trim($value, " \t\n\r\0\x0B'\""),
            $this->typeArguments()
        );

        if ($values === [] || in_array('', $values, true)) {
            throw new InvalidArgumentException('enum 字段至少需要一个非空枚举值：'.$this->name);
        }

        return $values;
    }

    private function decimalCast(): string
    {
        $scale = 0;
        if (preg_match('/\((\d+)\s*,\s*(\d+)\)/', $this->type, $matches) === 1) {
            $scale = (int) $matches[2];
        }

        return "'decimal:".$scale."'";
    }

    private function export(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return var_export($value, true);
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === 'true' || $trimmed === 'false') {
                return $trimmed;
            }

            if (preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/', $trimmed) === 1) {
                return $trimmed;
            }

            return var_export($value, true);
        }

        return var_export($value, true);
    }

    private static function toBool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'y', 'on', 'nullable'], true);
    }
}
