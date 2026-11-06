<?php

declare(strict_types=1);

namespace Readfly\Laravel\Console\Concerns;

use Readfly\Laravel\Console\FieldDefinition;
use Readfly\Laravel\Support\Path;

trait BuildsScaffoldContext
{
    protected function templatePath(): string
    {
        $custom = config('toolkit.templates_path');

        return is_string($custom) && $custom !== '' ? $custom : Path::package('resources/templates');
    }

    protected function authoringContext(): array
    {
        return [
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'author' => (string) config('toolkit.author', 'readfly'),
            'author_email' => (string) config('toolkit.author_email', 'wucc513721@163.com'),
            'company' => (string) config('toolkit.company', '达州市飞鸟网络科技有限公司'),
        ];
    }

    protected function scaffoldFields(string $question = '是否现在定义表字段？'): array
    {
        $input = (string) ($this->option('fields') ?: '');

        if ($input === '' && $this->input->isInteractive() && $this->confirmOrDefault($question, false)) {
            $input = (string) $this->ask('字段定义（name:string(64):名称;age:int:年龄=0）', '');
        }

        $fields = FieldDefinition::parseList($input);
        unset($fields['id'], $fields['created_at'], $fields['updated_at']);

        return $fields;
    }

    protected function migrationFieldLines(array $fields): string
    {
        return implode("\n", array_map(
            static fn (FieldDefinition $field): string => '            '.$field->migrationLine(),
            array_values($fields)
        ));
    }

    protected function fillableLines(array $fields): string
    {
        return implode("\n", array_map(
            static fn (FieldDefinition $field): string => "        '".$field->name."',",
            array_values($fields)
        ));
    }

    protected function castLines(array $fields): string
    {
        $lines = [];

        foreach ($fields as $field) {
            if ($field->cast() !== null) {
                $lines[] = "        '".$field->name."' => ".$field->cast().',';
            }
        }

        return implode("\n", $lines);
    }

    protected function ruleLines(array $fields): string
    {
        $lines = [];

        foreach ($fields as $field) {
            $rules = array_map(static fn (string $rule): string => "'".$rule."'", $field->rules());
            $lines[] = "            '".$field->name."' => [".implode(', ', $rules).'],';
        }

        return implode("\n", $lines);
    }

    protected function attributeLines(array $fields): string
    {
        $lines = [];

        foreach ($fields as $field) {
            $lines[] = "            '".$field->name."' => '".str_replace("'", "\\'", $field->comment)."',";
        }

        return implode("\n", $lines);
    }
}