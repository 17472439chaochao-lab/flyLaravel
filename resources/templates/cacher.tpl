<?php
/**
 * Power by readfly/laravel.
 * User: {{ author }} <{{ author_email }}>
 * Company: {{ company }}
 * Date: {{ date }}
 * Time: {{ time }}
 */

namespace {{ cache_namespace }};

use Readfly\Laravel\Modules\CacheModule;
use {{ repository_namespace }}\{{ repository_class }};

/**
 * {{ data_name }}数据缓存处理器
 */
class {{ cache_class }} extends CacheModule
{
    public function __construct()
    {
        parent::__construct('{{ cache_name }}', {{ cache_expire }}, '{{ cache_driver }}');
        $this->init();
    }

    public function refresh(): array
    {
        $this->clear();

        return $this->init();
    }

    protected function init(): array
    {
        if ($this->cache !== []) {
            return $this->cache;
        }

        $repository = new {{ repository_class }}();
        $data = [];
        $this->write($data);

        return $data;
    }
}