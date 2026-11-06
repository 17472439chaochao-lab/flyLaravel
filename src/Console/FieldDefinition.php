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
    ) {
    }

    public static function parse(string $definition): self
    {
        $segments = array_map('trim', explode(':', trim($definition)));
        $name = $segments[0] ?? '';

        if ($name === '' || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException('非法字段名：'.$definition);
        }

        $type = $segments[1] ?? 'string';
        $comment = $segments[2] ?? Str::headline($name);
        $default = $segments[3] ?? null;
        $nullable = isset($segments[4]) ? self::toBool($segments[4]) : false;

        if ($default === null && str_contains($comment, '=')) {
            [$comment, $default] = explode('=', $comment, 2);
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

        return new self($name, strtolower($type), $comment, $default, $nullable, $unsigned);
    }

    public static function parseList(string $definitions): array
    {
        if (trim($definitions) === '') {
            return [];
        }

        $items = preg_split('/[;|]/', $definitions) ?: [];
        $fields = [];

        foreach ($items as $item) {
            $item = trim($item);

            if ($item === '') {
                continue;
            }

            $field = self::parse($item);
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
        $method = self::TYPE_ALIASES[$base] ?? Str::camel($base);

        if (! method_exists(Blueprint::class, $method)) {
            throw new InvalidArgumentException('不支持的字段类型：'.$this->type);
        }

        return $method;
    }

    public function arguments(): array
    {
        $arguments = ["'".$this->name."'"];

        if (preg_match('/\((.*)\)$/', $this->type, $matches)) {
            foreach (array_map('trim', explode(',', $matches[1])) as $argument) {
                $arguments[] = is_numeric($argument)
                    ? $argument
                    : "'".str_replace("'", "\\'", $argument)."'";
            }
        }

        return $arguments;
    }

    public function migrationLine(): string
    {
        $arguments = $this->arguments();

        if ($this->baseType() === 'enum' && count($arguments) > 2) {
            $arguments = [$arguments[0], '['.implode(', ', array_slice($arguments, 1)).']'];
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

        return $line."->comment('".str_replace("'", "\\'", $this->comment)."');";
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

        if (preg_match('/\((\d+)/', $this->type, $matches)) {
            $max = (int) $matches[1];
        }

        $rules = match ($base) {
            'int', 'integer', 'bigint', 'smallint', 'tinyint', 'mediumint' => ['integer'],
            'decimal', 'float', 'double' => ['numeric'],
            'bool', 'boolean' => ['boolean'],
            'json', 'jsonb' => ['array'],
            'date' => ['date'],
            'datetime', 'timestamp' => ['date'],
            'uuid' => ['uuid'],
            'email' => ['email', 'max:'.$max],
            'url' => ['url', 'max:'.$max],
            'text', 'mediumtext', 'longtext' => ['string'],
            default => ['string', 'max:'.$max],
        };

        if ($this->nullable) {
            array_unshift($rules, 'nullable');
        } else {
            array_unshift($rules, 'required');
        }

        return $rules;
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
        ];
    }

    private function decimalCast(): string
    {
        $scale = 0;
        if (preg_match('/\((\d+)\s*,\s*(\d+)\)/', $this->type, $matches)) {
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
            return (string) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return $value;
        }

        if (in_array($value, ['true', 'false'], true)) {
            return $value;
        }

        return "'".str_replace("'", "\\'", (string) $value)."'";
    }

    private static function toBool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'y', 'on', 'nullable'], true);
    }
}