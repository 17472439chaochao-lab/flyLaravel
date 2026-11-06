<?php

declare(strict_types=1);

namespace Readfly\Laravel\Libraries;

use Readfly\Laravel\Libraries\Concerns\CreatesImageManager;
use Readfly\Laravel\Support\File;
use Readfly\Laravel\Support\Path;

class PictureLibrary
{
    use CreatesImageManager;

    private mixed $image;

    private string $source_path;

    public function __construct(string $source_path)
    {
        $this->source_path = $source_path;
        $manager = $this->imageManager();
        $this->image = method_exists($manager, 'read') ? $manager->read($source_path) : $manager->make($source_path);
    }

    public static function make(string $source_path): static
    {
        return new static($source_path);
    }

    public function drawl(string $picture, int $width = 100, int $height = 100, int $x = 0, int $y = 0, string $position = 'top-left'): static
    {
        $manager = $this->imageManager();
        $watermark = method_exists($manager, 'read')
            ? $manager->read($picture)->resize($width, $height)
            : $manager->make($picture)->resize($width, $height);

        if (method_exists($this->image, 'place')) {
            $this->image->place($watermark, $position, $x, $y);
        } else {
            $this->image->insert($watermark, $position, $x, $y);
        }

        return $this;
    }

    public function text(string $string, int $x = 0, int $y = 0, int $font_size = 20, string $color = '#000000', string $align_position = 'left', string $valign_position = 'top', string $ttf = ''): static
    {
        $ttf = $ttf !== '' ? $ttf : Path::package('data/AlibabaPuHuiTi.ttf');

        $this->image->text($string, $x, $y, function ($font) use ($font_size, $color, $ttf, $align_position, $valign_position): void {
            method_exists($font, 'filename') ? $font->filename($ttf) : $font->file($ttf);
            $font->size($font_size);
            $font->color($color);
            $font->align($align_position);
            $font->valign($valign_position);
        });

        return $this;
    }

    public function squares(string $prefix = 'square-', int $quality = 100, mixed $format = null): array
    {
        $paths = [];
        $width = $this->image->width();
        $height = $this->image->height();
        $numbers = (int) ceil($height / max(1, $width));
        $path_info = pathinfo($this->source_path);

        for ($i = 0; $i < $numbers; $i++) {
            $new_path = $path_info['dirname'].DIRECTORY_SEPARATOR.$prefix.$i.'_'.$path_info['basename'];
            File::copy($this->source_path, $new_path);

            $manager = $this->imageManager();
            $handler = method_exists($manager, 'read') ? $manager->read($new_path) : $manager->make($new_path);
            $crop_height = min($width, $height - ($i * $width));
            $handler->crop($width, $crop_height, 0, $i * $width)->save($new_path, $quality);
            $paths[] = $new_path;
        }

        return $paths;
    }

    public function save(string $file_path, int $quality = 100, mixed $format = null): string
    {
        File::makeDirectory(dirname($file_path));
        $this->image->save($file_path, $quality);
        $this->image = null;

        return $file_path;
    }

}
