<?php

declare(strict_types=1);

namespace app\service;

use app\model\Setting;

class SettingService
{
    /**
     * @return array<string, string>
     */
    public static function group(string $group): array
    {
        return Setting::query()
            ->where('group', $group)
            ->pluck('value', 'key')
            ->all();
    }

    public static function get(string $group, string $key, string $default = ''): string
    {
        $value = Setting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->value('value');

        return $value === null ? $default : (string)$value;
    }

    public static function int(string $group, string $key, int $default = 0): int
    {
        $value = self::get($group, $key, (string)$default);
        return $value === '' ? $default : (int)$value;
    }

    /**
     * 赠金功能对外展示名称，默认「赠金」，可在系统设置里改为「酒水卡」等
     */
    public static function giftDisplayName(): string
    {
        $name = self::get('point', 'gift_display_name', '赠金');
        return $name === '' ? '赠金' : $name;
    }

    /**
     * 赠金计量单位：元(按分记账，展示2位小数) 或 张(按整数记账)
     */
    public static function giftUnit(): string
    {
        return self::get('point', 'gift_unit', '元') === '张' ? '张' : '元';
    }

    /**
     * 饮品卡功能对外展示名称，默认「饮品卡」，可在系统设置里修改
     */
    public static function drinkCardDisplayName(): string
    {
        $name = self::get('point', 'drink_card_display_name', '饮品卡');
        return $name === '' ? '饮品卡' : $name;
    }

    /**
     * 饮品卡计量单位：元(按分记账，展示2位小数) 或 张(按整数记账)
     */
    public static function drinkCardUnit(): string
    {
        return self::get('point', 'drink_card_unit', '元') === '张' ? '张' : '元';
    }

    /**
     * 酒水卡(赠金)兑法币汇率，单位分/张(或分/元记账单位)，仅用于微信组合支付折算；0 表示不开启
     */
    public static function giftCashRate(): int
    {
        return self::yuanToFen(self::get('point', 'gift_cash_rate', '0'));
    }

    /**
     * 饮品卡兑法币汇率，单位分/张(或分/元记账单位)，仅用于微信组合支付折算；0 表示不开启
     */
    public static function drinkCardCashRate(): int
    {
        return self::yuanToFen(self::get('point', 'drink_card_cash_rate', '0'));
    }

    private static function yuanToFen(string $value): int
    {
        return $value === '' ? 0 : (int)round(((float)$value) * 100);
    }
}
