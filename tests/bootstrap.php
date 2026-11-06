<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

if (! function_exists('array_last')) {
    function array_last(array $array): mixed
    {
        return $array === [] ? null : end($array);
    }
}

if (! function_exists('array_first')) {
    function array_first(array $array): mixed
    {
        return $array === [] ? null : reset($array);
    }
}