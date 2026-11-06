<?php

declare(strict_types=1);

namespace Readfly\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Readfly\Laravel\Tools\DocParserTool;

class DocParserToolTest extends TestCase
{
    public function test_parse_description_and_tags(): void
    {
        $doc = <<<'DOC'
/**
 * 获取用户信息
 *
 * @param string $name 用户名称
 * @param int $age 年龄
 * @return array
 * @author readfly
 */
DOC;

        $params = (new DocParserTool())->parse($doc);

        $this->assertSame('获取用户信息', $params['description']);
        $this->assertSame(['type' => 'string', 'value' => '$name 用户名称'], $params['param'][0]);
        $this->assertSame(['type' => 'int', 'value' => '$age 年龄'], $params['param'][1]);
        $this->assertSame(['type' => 'array', 'value' => ''], $params['return']);
        $this->assertSame('readfly', $params['author']);
    }

    public function test_empty_doc_returns_empty_array(): void
    {
        $this->assertSame([], (new DocParserTool())->parse(''));
    }
}