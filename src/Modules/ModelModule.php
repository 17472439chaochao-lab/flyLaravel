<?php

declare(strict_types=1);

namespace Readfly\Laravel\Modules;

use Illuminate\Database\Eloquent\Model;

class ModelModule extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public const FIELD_ORDER_BY = 'field()';
    public const RANDOM_ORDER_BY = 'random_order()';
    public const RAW_ORDER_BY = 'raw()';

    public const STATUS_ENABLED = 1;
    public const STATUS_DISABLED = 2;
    public const STATUS_VERIFYING = 3;
    public const STATUS_VERIFY_FAILED = 4;
    public const STATUS_DELETED = 5;

    public const SWITCH_ON = 1;
    public const SWITCH_OFF = 2;

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ENABLED => '正常启用',
            self::STATUS_DISABLED => '禁用中',
            self::STATUS_VERIFYING => '审核中',
            self::STATUS_VERIFY_FAILED => '审核失败',
            self::STATUS_DELETED => '已删除',
        ];
    }

    public static function switchOptions(): array
    {
        return [
            self::SWITCH_ON => '是',
            self::SWITCH_OFF => '不是',
        ];
    }
}