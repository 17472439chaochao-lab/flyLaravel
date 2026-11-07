<?php
/**
 * Power by readfly/laravel.
 * User: {{ author }} <{{ author_email }}>
 * Company: {{ company }}
 * Date: {{ date }}
 * Time: {{ time }}
 */

namespace {{ model_namespace }};

use Readfly\Laravel\Modules\ModelModule;
{{#if has_soft_deletes}}
use Illuminate\Database\Eloquent\SoftDeletes;
{{/if}}
{{#if has_relations}}
use Illuminate\Database\Eloquent\Relations\BelongsTo;
{{/if}}

/**
 * {{ table_comment }}
 */
class {{ model_class }} extends ModelModule
{
{{#if has_soft_deletes}}
    use SoftDeletes;

{{/if}}
    protected $table = self::TABLE_NAME;

    protected $connection = '{{ db_connection }}';

    public const TABLE_NAME = '{{ table_name }}';

    public const DB_CONNECTION = '{{ db_connection }}';

    public const AUTO_TIMESTAMP = {{ auto_timestamp_code }};

{{#if id_is_string}}
    protected $keyType = 'string';

    public $incrementing = false;

{{/if}}
    protected $fillable = [
{{ fillable_code }}
    ];

    protected $casts = [
{{ casts_code }}
    ];

    public const TYPE_GROUPS = [
        '__switch__' => [self::SWITCH_ON => '是', self::SWITCH_OFF => '不是'],
        '__status__' => [
            self::STATUS_ENABLED => '正常启用',
            self::STATUS_DISABLED => '禁用中',
            self::STATUS_VERIFYING => '审核中',
            self::STATUS_VERIFY_FAILED => '审核失败',
            self::STATUS_DELETED => '已删除',
        ],
    ];

    public const THEME_GROUPS = [
    ];

{{#if has_relations}}
{{ relations_code }}
{{/if}}
}
