<?php

declare(strict_types=1);

namespace Readfly\Laravel\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Readfly\Laravel\Console\FieldDefinition;

class FieldDefinitionTest extends TestCase
{
    public function test_parse_string_field(): void
    {
        $field = FieldDefinition::parse('name:string(64):用户名称');

        $this->assertSame('name', $field->name);
        $this->assertSame('string', $field->method());
        $this->assertSame("\$table->string('name', 64)->comment('用户名称');", $field->migrationLine());
        $this->assertSame('string', $field->baseType());
        $this->assertSame(['required', 'string', 'max:64'], $field->rules());
    }

    public function test_parse_nullable_json_with_cast(): void
    {
        $field = FieldDefinition::parse('meta:?json:扩展信息');

        $this->assertTrue($field->nullable);
        $this->assertSame("'array'", $field->cast());
        $this->assertSame(['nullable', 'array'], $field->rules());
        $this->assertStringContainsString('->nullable()', $field->migrationLine());
    }

    public function test_decimal_type_and_cast(): void
    {
        $field = FieldDefinition::parse('price:decimal(10,2):价格=0');

        $this->assertSame('decimal', $field->method());
        $this->assertSame("'decimal:2'", $field->cast());
        $this->assertSame(['required', 'numeric'], $field->rules());
        $this->assertStringContainsString("->default(0)", $field->migrationLine());
    }

    public function test_enum_arguments_are_rendered_as_array(): void
    {
        $field = FieldDefinition::parse('status:enum(enabled,disabled):状态');

        $this->assertSame(
            "\$table->enum('status', ['enabled', 'disabled'])->comment('状态');",
            $field->migrationLine()
        );
    }

    public function test_parse_list(): void
    {
        $fields = FieldDefinition::parseList('name:string(32):名称;age:int:年龄=0|status:tinyint:状态=1');

        $this->assertCount(3, $fields);
        $this->assertArrayHasKey('name', $fields);
        $this->assertArrayHasKey('age', $fields);
        $this->assertArrayHasKey('status', $fields);
        $this->assertSame('1', (string) $fields['status']->default);
    }

    public function test_invalid_field_name_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FieldDefinition::parse("name'; drop table users; --:string:名称");
    }

    public function test_unsupported_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FieldDefinition::parse('name:unknown_type:名称')->migrationLine();
    }
}