<?php

declare(strict_types=1);

namespace app\model;

/**
 * 接单打印机配置
 */
class Printer extends BaseModel
{
    public const int VENDOR_FEIE  = 1;
    public const int VENDOR_XPYUN = 2;
    public const int VENDOR_SUNMI = 3;

    public const int STATUS_ON  = 1;
    public const int STATUS_OFF = 0;

    protected $table = 'nf_printer';

    protected $casts = [
        'vendor'      => 'integer',
        'copies'      => 'integer',
        'voice_times' => 'integer',
        'status'      => 'integer',
        'sort'        => 'integer',
        'created_at'  => 'datetime:Y-m-d H:i:s',
        'updated_at'  => 'datetime:Y-m-d H:i:s',
    ];

    /** @return array<int, string> */
    public static function vendors(): array
    {
        return [self::VENDOR_FEIE, self::VENDOR_XPYUN, self::VENDOR_SUNMI];
    }
}
