<?php

use Illuminate\Support\Facades\Session;

if (! function_exists('auth_name')) {
    function auth_name(string $prefix = 'your-project-alias:account'): string
    {
        return (! empty($prefix) ? $prefix : 'your-project-alias:account').'_auth_info';
    }
}

if (! function_exists('current_auth')) {
    function current_auth(mixed $item = false, string $prefix = 'your-project-alias:account'): mixed
    {
        $auth = Session::get(auth_name($prefix));

        if ($auth === null) {
            return false;
        }

        return $item !== false && $item !== '' ? data_get($auth, $item, false) : $auth;
    }
}

if (! function_exists('set_current_auth')) {
    function set_current_auth(mixed $auth, string $prefix = 'your-project-alias:account'): mixed
    {
        return Session::put(auth_name($prefix), $auth);
    }
}

if (! function_exists('auth_remove')) {
    function auth_remove(string $prefix = 'your-project-alias:account', bool $clear_all = false): bool
    {
        Session::forget(auth_name($prefix));

        if ($clear_all) {
            Session::flush();
        }

        return true;
    }
}