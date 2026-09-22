<?php

declare(strict_types=1);

namespace app\service;

use RuntimeException;

/**
 * 腾讯云 COS 图片存储服务。
 * 未引入官方 SDK，直接用 curl + COS 签名v5算法（HMAC-SHA1），
 * 避免给项目新增大体积依赖（与 WechatPayService 手写签名的风格保持一致）。
 * 文档：https://cloud.tencent.com/document/product/436/7778
 */
class CosService
{
    private const array MIME_MAP = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
    ];

    /**
     * 把本地文件上传到 COS，返回对外可访问的完整 URL
     */
    public static function uploadFile(string $localPath, string $key, string $extension = ''): string
    {
        $config = self::config();
        if ($config['secret_id'] === '' || $config['secret_key'] === '' || $config['bucket'] === '' || $config['domain'] === '') {
            throw new RuntimeException('COS 未配置完整（COS_SECRET_ID/COS_SECRET_KEY/COS_BUCKET/COS_DOMAIN）');
        }

        $body = file_get_contents($localPath);
        if ($body === false) {
            throw new RuntimeException('读取待上传文件失败：' . $localPath);
        }

        $key     = ltrim($key, '/');
        $host    = "{$config['bucket']}.cos.{$config['region']}.myqcloud.com";
        $urlPath = '/' . implode('/', array_map('rawurlencode', explode('/', $key)));

        $authorization = self::sign('put', $urlPath, $host, $config['secret_id'], $config['secret_key']);
        $contentType   = self::MIME_MAP[strtolower($extension)] ?? 'application/octet-stream';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => "https://{$host}{$urlPath}",
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'Host: ' . $host,
                'Content-Type: ' . $contentType,
                'Authorization: ' . $authorization,
            ],
        ]);

        $response = curl_exec($ch);
        $status   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('上传 COS 失败：' . $error);
        }
        if ($status !== 200) {
            throw new RuntimeException('上传 COS 失败，HTTP状态码：' . $status . ' ' . (string)$response);
        }

        return $config['domain'] . '/' . $key;
    }

    private static function sign(string $method, string $urlPath, string $host, string $secretId, string $secretKey): string
    {
        $startTime = time() - 60;
        $endTime   = $startTime + 3600;
        $keyTime   = "{$startTime};{$endTime}";
        $signKey   = hash_hmac('sha1', $keyTime, $secretKey);

        // 本项目只用最简单的 PutObject（无查询参数），签名头只需要 host
        $headerList  = ['host' => $host];
        $headerParts = [];
        foreach ($headerList as $k => $v) {
            $headerParts[] = rawurlencode($k) . '=' . rawurlencode($v);
        }
        $headerString  = implode('&', $headerParts);
        $headerKeyList = implode(';', array_keys($headerList));

        $httpString   = strtolower($method) . "\n{$urlPath}\n\n{$headerString}\n";
        $stringToSign = "sha1\n{$keyTime}\n" . sha1($httpString) . "\n";
        $signature    = hash_hmac('sha1', $stringToSign, $signKey);

        return 'q-sign-algorithm=sha1&q-ak=' . $secretId
            . '&q-sign-time=' . $keyTime
            . '&q-key-time=' . $keyTime
            . '&q-header-list=' . $headerKeyList
            . '&q-url-param-list='
            . '&q-signature=' . $signature;
    }

    /**
     * @return array{secret_id: string, secret_key: string, region: string, bucket: string, domain: string}
     */
    public static function config(): array
    {
        return [
            'secret_id'  => (string)config('cos.secret_id', ''),
            'secret_key' => (string)config('cos.secret_key', ''),
            'region'     => (string)config('cos.region', ''),
            'bucket'     => (string)config('cos.bucket', ''),
            'domain'     => rtrim((string)config('cos.domain', ''), '/'),
        ];
    }
}
