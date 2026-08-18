<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\support\Cache;
use RuntimeException;

/**
 * 微信小程序开放接口
 */
class WechatService
{
    private const string API_BASE = 'https://api.weixin.qq.com';

    /**
     * code 换取 openid
     *
     * @return array{openid: string, unionid: string, session_key: string}
     */
    public static function code2Session(string $code): array
    {
        [$appid, $secret] = self::credentials();

        $result = self::get('/sns/jscode2session', [
            'appid'      => $appid,
            'secret'     => $secret,
            'js_code'    => $code,
            'grant_type' => 'authorization_code',
        ]);

        if (empty($result['openid'])) {
            throw new BusinessException('微信登录失败，请重试');
        }

        return [
            'openid'      => (string)$result['openid'],
            'unionid'     => (string)($result['unionid'] ?? ''),
            'session_key' => (string)($result['session_key'] ?? ''),
        ];
    }

    /**
     * 获取手机号（新版 code 换取方式）
     */
    public static function getPhoneNumber(string $code): string
    {
        $result = self::post('/wxa/business/getuserphonenumber', ['code' => $code], true);
        $phone  = $result['phone_info']['purePhoneNumber'] ?? '';
        if ($phone === '') {
            throw new BusinessException('手机号获取失败，请重试');
        }

        return (string)$phone;
    }

    /**
     * 生成小程序码，返回 base64 图片
     */
    public static function getUnlimitedQrCode(string $scene, string $page = 'pages/index/index'): string
    {
        $token   = self::accessToken();
        $url     = self::API_BASE . '/wxa/getwxacodeunlimit?access_token=' . urlencode($token);
        $payload = json_encode([
            'scene'      => $scene,
            'page'       => $page,
            'check_path' => false,
            'env_version' => 'release',
        ], JSON_UNESCAPED_UNICODE);

        $response = self::request('POST', $url, $payload, ['Content-Type: application/json']);

        // 成功时返回图片二进制流，失败时返回 JSON
        $json = json_decode($response, true);
        if (is_array($json) && isset($json['errcode']) && (int)$json['errcode'] !== 0) {
            throw new BusinessException('小程序码生成失败：' . ($json['errmsg'] ?? 'unknown'));
        }

        return 'data:image/png;base64,' . base64_encode($response);
    }

    public static function accessToken(bool $refresh = false): string
    {
        $cacheKey = 'wechat:access_token';
        if (!$refresh) {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        [$appid, $secret] = self::credentials();
        $result = self::get('/cgi-bin/token', [
            'grant_type' => 'client_credential',
            'appid'      => $appid,
            'secret'     => $secret,
        ]);

        $token = (string)($result['access_token'] ?? '');
        if ($token === '') {
            throw new BusinessException('微信凭证获取失败：' . ($result['errmsg'] ?? 'unknown'));
        }

        Cache::set($cacheKey, $token, max(60, (int)($result['expires_in'] ?? 7200) - 300));

        return $token;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function credentials(): array
    {
        $appid  = (string)config('wechat.appid', '');
        $secret = (string)config('wechat.secret', '');
        if ($appid === '' || $secret === '') {
            throw new BusinessException('未配置微信小程序 AppID / AppSecret');
        }

        return [$appid, $secret];
    }

    /**
     * @param array<string, string> $query
     * @return array<string, mixed>
     */
    private static function get(string $path, array $query): array
    {
        $response = self::request('GET', self::API_BASE . $path . '?' . http_build_query($query));
        return self::decode($response);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function post(string $path, array $data, bool $withToken = false): array
    {
        $url = self::API_BASE . $path;
        if ($withToken) {
            $url .= '?access_token=' . urlencode(self::accessToken());
        }

        $response = self::request('POST', $url, json_encode($data, JSON_UNESCAPED_UNICODE), ['Content-Type: application/json']);
        return self::decode($response);
    }

    /**
     * @param array<int, string> $headers
     */
    private static function request(string $method, string $url, ?string $body = null, array $headers = []): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('请求微信接口失败：' . $error);
        }

        return (string)$response;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $response): array
    {
        $result = json_decode($response, true);
        if (!is_array($result)) {
            throw new RuntimeException('微信接口返回异常：' . $response);
        }
        if (isset($result['errcode']) && (int)$result['errcode'] !== 0) {
            throw new BusinessException('微信接口错误：' . ($result['errmsg'] ?? 'unknown'));
        }

        return $result;
    }
}
