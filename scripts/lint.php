<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$roots = ['src', 'functions', 'config', 'scripts', 'tests'];
$files = [];

foreach ($roots as $directory) {
    $path = $root.'/'.$directory;

    if (! is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

foreach (['phpstan-bootstrap.php', 'phpstan-stubs.php'] as $file) {
    if (is_file($root.'/'.$file)) {
        $files[] = $root.'/'.$file;
    }
}

sort($files);
$failed = 0;

foreach ($files as $file) {
    $output = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1', $output, $code);

    if ($code !== 0) {
        $failed++;
        fwrite(STDERR, implode("\n", $output)."\n");
    }
}

if ($failed > 0) {
    fwrite(STDERR, $failed.' file(s) failed syntax check.'."\n");
    exit(1);
}

echo 'Linted '.count($files).' PHP files successfully.'."\n";
