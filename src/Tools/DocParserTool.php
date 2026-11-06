<?php

declare(strict_types=1);

namespace Readfly\Laravel\Tools;

class DocParserTool
{
    protected array $params = [];

    public function parse(string $doc = ''): array
    {
        $this->params = [];

        if ($doc === '' || preg_match('#^/\*\*(.*)\*/#s', $doc, $comment) !== 1) {
            return $this->params;
        }

        $comment = trim($comment[1]);

        if (preg_match_all('#^\s*\*(.*)#m', $comment, $lines) === false) {
            return $this->params;
        }

        $description = [];

        foreach ($lines[1] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '@')) {
                $this->registerTag($line);
                continue;
            }

            $description[] = $line;
        }

        if ($description !== []) {
            $this->params['description'] = implode(' ', $description);
        }

        return $this->params;
    }

    protected function registerTag(string $line): void
    {
        $position = strpos($line, ' ');
        $tag = $position === false ? substr($line, 1) : substr($line, 1, $position - 1);
        $value = $position === false ? '' : substr($line, $position + 1);

        if ($tag === 'param') {
            $value = $this->formatTypedValue($value);
        }

        if ($tag === 'return') {
            $value = $this->formatTypedValue($value);
        }

        if ($tag === 'class') {
            [$tag, $value] = $this->formatClass($value);
        }

        if ($tag === 'param') {
            $this->params['param'][] = $value;

            return;
        }

        if (! isset($this->params[$tag])) {
            $this->params[$tag] = $value;

            return;
        }

        $this->params[$tag] = (int) $this->params[$tag] + (int) $value;
    }

    protected function formatTypedValue(string $value): array
    {
        $position = strpos($value, ' ');

        if ($position === false) {
            return ['type' => $value, 'value' => ''];
        }

        return [
            'type' => substr($value, 0, $position),
            'value' => substr($value, $position + 1),
        ];
    }

    protected function formatClass(string $value): array
    {
        $segments = preg_split('/[()]/', $value) ?: [];
        $name = $segments[0] ?? 'Unknown';
        $attributes = [];

        if (isset($segments[1]) && $segments[1] !== '') {
            parse_str($segments[1], $attributes);
        }

        return [$name, $attributes];
    }
}