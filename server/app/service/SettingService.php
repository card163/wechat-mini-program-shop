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
}
