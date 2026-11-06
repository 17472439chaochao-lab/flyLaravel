<?php

declare(strict_types=1);

namespace Readfly\Laravel;

use Readfly\Laravel\Support\File;
use Readfly\Laravel\Support\Path;

class ToolkitEvents
{
    public static function autoload(): bool
    {
        $source = Path::package('data/avatars');
        $target = function_exists('config')
            ? (string) config('toolkit.assets.avatars', 'static/avatars')
            : 'static/avatars';

        $target = Path::public($target);

        File::makeDirectory($target);

        foreach (File::files($source) as $file) {
            $destination = $target.'/'.$file['basename'];

            if (! file_exists($destination)) {
                File::copy($file['path'], $destination);
            }
        }

        return true;
    }
}