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

    public function test_enum_values_preserve_case_and_single_value_is_array(): void
    {
        $field = FieldDefinition::parse('status:enum(Active):状态');

        $this->assertSame("\$table->enum('status', ['Active'])->comment('状态');", $field->migrationLine());
        $this->assertSame(['required', 'string', "Rule::in(['Active'])"], $field->rules());
    }

    public function test_numeric_default_with_leading_zero_is_quoted(): void
    {
        $field = FieldDefinition::parse('code:string(2):编码=08');

        $this->assertSame("\$table->string('code', 2)->default('08')->comment('编码');", $field->migrationLine());
    }

    public function test_backslash_default_is_exported_safely(): void
    {
        $field = FieldDefinition::parse('path:string(64):路径=foo\\bar');

        $this->assertStringContainsString("->default('foo\\\\bar')", $field->migrationLine());
    }

    public function test_quoted_values_can_contain_colons_and_backslashes(): void
    {
        $field = FieldDefinition::parse('path:string(64):路径:"C:\\\\"');
        $comment = FieldDefinition::parse('name:string(64):"用户:名称"');

        $this->assertSame("\$table->string('path', 64)->default('C:\\\\')->comment('路径');", $field->migrationLine());
        $this->assertSame("\$table->string('name', 64)->comment('用户:名称');", $comment->migrationLine());
    }

    public function test_list_separator_inside_quoted_value_is_preserved(): void
    {
        $fields = FieldDefinition::parseList('name:string(64):"a|b";age:int:年龄=0');

        $this->assertCount(2, $fields);
        $this->assertSame('a|b', $fields['name']->comment);
    }

    public function test_equals_inside_quoted_comment_is_not_a_default(): void
    {
        $field = FieldDefinition::parse('name:string(64):"a=b"');

        $this->assertSame('a=b', $field->comment);
        $this->assertNull($field->default);
        $this->assertStringContainsString("comment('a=b')", $field->migrationLine());
    }

    public function test_custom_rules_can_be_added_to_field(): void
    {
        $field = FieldDefinition::parse('email:string(64):邮箱::false:"required|email|unique:users,email"');

        $this->assertContains('unique:users,email', $field->rules());
        $this->assertContains('email', $field->rules());
        $this->assertSame(1, count(array_keys($field->rules(), 'required', true)));
    }

    public function test_json_fields_are_supported(): void
    {
        $fields = FieldDefinition::parseJson(json_encode([
            ['name' => 'name', 'type' => 'string(64)', 'comment' => '名称'],
            ['name' => 'age', 'type' => 'int', 'comment' => '年龄', 'default' => 0, 'rules' => ['integer', 'min:1']],
        ], JSON_UNESCAPED_UNICODE));

        $this->assertCount(2, $fields);
        $this->assertSame('string', $fields['name']->baseType());
        $this->assertSame(['required', 'integer', 'min:1'], $fields['age']->rules());
    }
}
