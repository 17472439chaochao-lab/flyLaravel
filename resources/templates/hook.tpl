<?php
/**
 * Power by readfly/laravel.
 * User: {{ author }} <{{ author_email }}>
 * Company: {{ company }}
 * Date: {{ date }}
 * Time: {{ time }}
 */

namespace {{ hook_namespace }};

use Exception;

/**
 * {{ data_name }}事件钩子
 */
class {{ hook_class }}
{
    public static function create(mixed $unique_key, array $extras = []): bool
    {
        return true;
    }

    public static function update(mixed $unique_key, array $extras = []): bool
    {
        return true;
    }

    public static function delete(mixed $unique_key, array $extras = []): bool
    {
        return true;
    }
}