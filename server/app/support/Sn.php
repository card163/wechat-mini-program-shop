<?php

declare(strict_types=1);

namespace app\support;

use Illuminate\Support\Str;

class Sn
{
    public const string ORDER    = 'NF';
    public const string RECHARGE = 'RC';
    public const string EXCHANGE = 'EX';
    public const string WINE_TAKE = 'WT';

    /**
     * 生成业务单号：前缀 + 年月日时分秒 + 6位随机数
     */
    public static function make(string $prefix): string
    {
        return $prefix . date('YmdHis') . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public static function random(int $length = 32): string
    {
        return Str::random($length);
    }
}
