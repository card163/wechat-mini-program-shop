<?php

declare(strict_types=1);

namespace app\support;

/**
 * 轻量文件缓存，用于 access_token 等短期数据（单机部署足够；多机部署应替换为 Redis）
 */
class Cache
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $file = self::path($key);
        if (!is_file($file)) {
            return $default;
        }

        $payload = json_decode((string)file_get_contents($file), true);
        if (!is_array($payload) || !isset($payload['expire_at'], $payload['value'])) {
            return $default;
        }
        if ($payload['expire_at'] > 0 && $payload['expire_at'] < time()) {
            @unlink($file);
            return $default;
        }

        return $payload['value'];
    }

    public static function set(string $key, mixed $value, int $ttl = 0): void
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(self::path($key), json_encode([
            'expire_at' => $ttl > 0 ? time() + $ttl : 0,
            'value'     => $value,
        ], JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    public static function delete(string $key): void
    {
        $file = self::path($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private static function dir(): string
    {
        return runtime_path() . '/cache';
    }

    private static function path(string $key): string
    {
        return self::dir() . '/' . md5($key) . '.cache';
    }
}
