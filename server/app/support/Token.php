<?php

declare(strict_types=1);

namespace app\support;

use app\exception\BusinessException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

class Token
{
    public const string GUARD_MEMBER = 'member';
    public const string GUARD_ADMIN  = 'admin';

    /**
     * @param array<string, mixed> $claims 附加载荷，如 role
     */
    public static function issue(string $guard, int $uid, array $claims = []): array
    {
        $config = self::config($guard);
        $now    = time();
        $expire = $now + $config['ttl'];
        $token  = JWT::encode(array_merge($claims, [
            'iss' => $guard,
            'uid' => $uid,
            'iat' => $now,
            'exp' => $expire,
        ]), $config['secret'], $config['alg']);

        return ['token' => $token, 'expires_at' => $expire];
    }

    /**
     * @return array<string, mixed>
     */
    public static function verify(string $guard, string $token): array
    {
        $config = self::config($guard);
        try {
            $payload = (array)JWT::decode($token, new Key($config['secret'], $config['alg']));
        } catch (Throwable) {
            throw new BusinessException('登录已失效，请重新登录', Result::UNAUTHORIZED);
        }
        if (($payload['iss'] ?? '') !== $guard) {
            throw new BusinessException('登录已失效，请重新登录', Result::UNAUTHORIZED);
        }
        return $payload;
    }

    /**
     * @return array{secret: string, ttl: int, alg: string}
     */
    private static function config(string $guard): array
    {
        $config = config("jwt.$guard");
        if (empty($config['secret'])) {
            throw new BusinessException("未配置 {$guard} 端 JWT 密钥", Result::SERVER_ERROR);
        }
        return $config;
    }
}
