<?php

declare(strict_types=1);

namespace Readfly\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Readfly\Laravel\Console\StubRenderer;
use Readfly\Laravel\Support\File;

class StubRendererTest extends TestCase
{
    protected string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/toolkit-stubs-'.uniqid('', true);
        File::makeDirectory($this->directory);
        File::put($this->directory.'/demo.tpl', 'Hello {{ name }}!{{#if show}} VISIBLE={{ value }}{{/if}}{{#unless hide}} ALWAYS{{/unless}} LEGACY={__LEGACY__}');
    }

    protected function tearDown(): void
    {
        File::delete($this->directory);
    }

    public function test_render_replaces_placeholders_and_boundaries(): void
    {
        $renderer = new StubRenderer($this->directory);
        $content = $renderer->render('demo.tpl', [
            'name' => 'Readfly',
            'show' => true,
            'value' => 42,
            'hide' => false,
            'legacy' => 'L',
        ]);

        $this->assertSame('Hello Readfly! VISIBLE=42 ALWAYS LEGACY=L', $content);
    }

    public function test_conditional_blocks_can_be_hidden(): void
    {
        $renderer = new StubRenderer($this->directory);
        $content = $renderer->render('demo.tpl', [
            'name' => 'X',
            'show' => false,
            'value' => 1,
            'hide' => true,
            'legacy' => '',
        ]);

        $this->assertStringNotContainsString('VISIBLE', $content);
        $this->assertStringNotContainsString('ALWAYS', $content);
        $this->assertStringContainsString('Hello X!', $content);
    }
}