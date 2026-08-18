<?php

declare(strict_types=1);

namespace app\support;

use app\exception\BusinessException;

/**
 * 存酒码 scene 编解码
 *
 * 小程序码 scene 最长 32 个字符，因此格式压缩为 {memberId}.{timestamp}.{signature}
 */
class Scene
{
    private const int SIGNATURE_LENGTH = 10;

    public static function make(int $memberId, int $ttl = 600): array
    {
        $timestamp = time();
        $scene     = $memberId . '.' . $timestamp . '.' . self::sign($memberId, $timestamp);

        return ['scene' => $scene, 'expires_at' => $timestamp + $ttl];
    }

    /**
     * 校验并解析出会员ID
     */
    public static function parse(string $scene, int $ttl = 600): int
    {
        $parts = explode('.', $scene);
        if (count($parts) !== 3) {
            throw new BusinessException('无效的存酒码');
        }

        [$memberId, $timestamp, $signature] = $parts;
        $memberId  = (int)$memberId;
        $timestamp = (int)$timestamp;

        if ($memberId <= 0 || $timestamp <= 0) {
            throw new BusinessException('无效的存酒码');
        }
        if (!hash_equals(self::sign($memberId, $timestamp), $signature)) {
            throw new BusinessException('无效的存酒码');
        }
        if (time() - $timestamp > $ttl) {
            throw new BusinessException('存酒码已过期，请让会员重新生成');
        }

        return $memberId;
    }

    private static function sign(int $memberId, int $timestamp): string
    {
        $secret = (string)config('jwt.member.secret', '');
        if ($secret === '') {
            throw new BusinessException('系统未配置签名密钥', Result::SERVER_ERROR);
        }

        return substr(hash_hmac('sha256', $memberId . '.' . $timestamp, $secret), 0, self::SIGNATURE_LENGTH);
    }
}
