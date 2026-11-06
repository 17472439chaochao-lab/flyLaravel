<?php

declare(strict_types=1);

namespace Readfly\Laravel\Libraries;

use Illuminate\Support\Facades\Storage;
use Readfly\Laravel\Exceptions\ToolkitException;
use Readfly\Laravel\Libraries\Concerns\CreatesImageManager;
use Readfly\Laravel\Support\Path;

class AvatarLibrary
{
    use CreatesImageManager;

    private mixed $image = null;

    private int $height = 200;

    private string $background = '#F7F7F7';

    private string $color = '#333333';

    private string $ttf;

    public function __construct(int $height = 200, string $background = '#F7F7F7', string $color = '#333333')
    {
        $this->height = max(16, $height);
        $this->background($background)->color($color);
        $this->ttf = Path::package('data/AlibabaPuHuiTi.ttf');
    }

    public static function make(int $height = 200, string $ttf = ''): static
    {
        $instance = new static($height);

        if ($ttf !== '') {
            $instance->ttf($ttf);
        }

        return $instance;
    }

    public function ttf(string $ttf): static
    {
        $this->ttf = $ttf !== '' ? $ttf : Path::package('data/AlibabaPuHuiTi.ttf');

        return $this;
    }

    public function background(string $background = '#F8F8F8'): static
    {
        $this->background = $background;

        return $this;
    }

    public function color(string $color = '#333333'): static
    {
        $this->color = $color;

        return $this;
    }

    public function name(string $name): static
    {
        $length = max(1, mb_strlen($name));
        $font_size = (int) max(12, $this->height / $length / ($length <= 4 ? 1.5 : 1.1));

        $this->image = $this->imageManager()
            ->create($this->height, $this->height)
            ->fill($this->background)
            ->text($name, (int) ($this->height / 2), (int) ($this->height / 2), function ($font) use ($font_size): void {
                method_exists($font, 'filename')
                    ? $font->filename($this->ttf)
                    : $font->file($this->ttf);
                $font->size($font_size);
                $font->color($this->color);
                $font->align('center');
                $font->valign('middle');
            });

        return $this;
    }

    public function save(string $storage_name, int $quality = 100, string $storage_disk = 'public', bool $force_merge = true): array
    {
        if ($this->image === null) {
            throw new ToolkitException('请先调用 name() 生成头像内容');
        }

        $file = StorageFileLibrary::check($storage_name, $storage_disk, $force_merge);
        $this->image->save($file['storage_path'], $quality);
        $this->image = null;

        return array_merge($file, ['url' => Storage::disk($storage_disk)->url($storage_name)]);
    }

}
