<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use RuntimeException;

/**
 * 微信支付 V3（JSAPI）
 *
 * 证书与密钥全部来自 .env，禁止硬编码；未配置时直接拒绝发起支付。
 */
class WechatPayService
{
    private const string API_BASE  = 'https://api.mch.weixin.qq.com';
    private const string JSAPI_URL = '/v3/pay/transactions/jsapi';

    /**
     * 下单并返回小程序调起支付所需参数
     *
     * @return array<string, string>
     */
    public static function jsapiPay(string $outTradeNo, int $amount, string $description, string $openid): array
    {
        $config = self::config();

        $body = json_encode([
            'appid'        => $config['appid'],
            'mchid'        => $config['mch_id'],
            'description'  => mb_substr($description, 0, 127),
            'out_trade_no' => $outTradeNo,
            'notify_url'   => $config['notify_url'],
            'amount'       => ['total' => $amount, 'currency' => 'CNY'],
            'payer'        => ['openid' => $openid],
        ], JSON_UNESCAPED_UNICODE);

        $response = self::request('POST', self::JSAPI_URL, (string)$body);
        $prepayId = $response['prepay_id'] ?? '';
        if ($prepayId === '') {
            throw new BusinessException('微信支付下单失败');
        }

        return self::buildPayParams($config, 'prepay_id=' . $prepayId);
    }

    /**
     * 申请退款
     *
     * @return array<string, mixed>
     */
    public static function refund(string $outTradeNo, string $outRefundNo, int $refundAmount, int $totalAmount): array
    {
        $body = json_encode([
            'out_trade_no'  => $outTradeNo,
            'out_refund_no' => $outRefundNo,
            'amount'        => [
                'refund'   => $refundAmount,
                'total'    => $totalAmount,
                'currency' => 'CNY',
            ],
        ], JSON_UNESCAPED_UNICODE);

        return self::request('POST', '/v3/refund/domestic/refunds', (string)$body);
    }

    /**
     * 校验并解密支付回调
     *
     * @param array<string, string> $headers
     * @return array<string, mixed> 解密后的资源对象
     */
    public static function decodeNotify(array $headers, string $body): array
    {
        $config = self::config();

        self::verifySignature($config, $headers, $body);

        $payload  = json_decode($body, true);
        $resource = $payload['resource'] ?? null;
        if (!is_array($resource)) {
            throw new BusinessException('回调报文格式错误');
        }

        // AES-256-GCM 密文尾部 16 字节为认证标签
        $raw        = base64_decode((string)($resource['ciphertext'] ?? ''));
        $ciphertext = substr($raw, 0, -16);
        $tag        = substr($raw, -16);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $config['api_v3_key'],
            OPENSSL_RAW_DATA,
            (string)($resource['nonce'] ?? ''),
            $tag,
            (string)($resource['associated_data'] ?? '')
        );

        if ($plaintext === false) {
            throw new BusinessException('回调报文解密失败');
        }

        $data = json_decode($plaintext, true);
        if (!is_array($data)) {
            throw new BusinessException('回调报文解析失败');
        }

        return $data;
    }

    /**
     * @param array<string, string> $config
     * @return array<string, string>
     */
    private static function buildPayParams(array $config, string $package): array
    {
        $timestamp = (string)time();
        $nonceStr  = bin2hex(random_bytes(16));
        $message   = $config['appid'] . "\n" . $timestamp . "\n" . $nonceStr . "\n" . $package . "\n";

        return [
            'timeStamp' => $timestamp,
            'nonceStr'  => $nonceStr,
            'package'   => $package,
            'signType'  => 'RSA',
            'paySign'   => self::sign($config['key_path'], $message),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function request(string $method, string $path, string $body): array
    {
        $config    = self::config();
        $timestamp = (string)time();
        $nonce     = bin2hex(random_bytes(16));
        $message   = $method . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . $body . "\n";
        $signature = self::sign($config['key_path'], $message);

        $authorization = sprintf(
            'WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",signature="%s",timestamp="%s",serial_no="%s"',
            $config['mch_id'],
            $nonce,
            $signature,
            $timestamp,
            $config['serial_no']
        );

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => self::API_BASE . $path,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: nice-fold/1.0',
                'Authorization: ' . $authorization,
            ],
        ]);

        $response = curl_exec($ch);
        $status   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('请求微信支付失败：' . $error);
        }

        $result = json_decode((string)$response, true);
        if ($status >= 400 || !is_array($result)) {
            throw new BusinessException('微信支付接口错误：' . (is_array($result) ? ($result['message'] ?? '') : (string)$response));
        }

        return $result;
    }

    /**
     * @param array<string, string> $config
     * @param array<string, string> $headers
     */
    private static function verifySignature(array $config, array $headers, string $body): void
    {
        $certPath = $config['cert_path'];
        if ($certPath === '' || !is_file($certPath)) {
            throw new BusinessException('未配置微信支付平台证书，拒绝处理回调');
        }

        $timestamp = $headers['wechatpay-timestamp'] ?? '';
        $nonce     = $headers['wechatpay-nonce'] ?? '';
        $signature = $headers['wechatpay-signature'] ?? '';
        if ($timestamp === '' || $nonce === '' || $signature === '') {
            throw new BusinessException('回调签名头缺失');
        }
        if (abs(time() - (int)$timestamp) > 300) {
            throw new BusinessException('回调时间戳超出允许范围');
        }

        $publicKey = openssl_pkey_get_public((string)file_get_contents($certPath));
        if ($publicKey === false) {
            throw new BusinessException('微信支付平台证书无效');
        }

        $message = $timestamp . "\n" . $nonce . "\n" . $body . "\n";
        $ok = openssl_verify($message, base64_decode($signature), $publicKey, 'sha256WithRSAEncryption');
        if ($ok !== 1) {
            throw new BusinessException('回调签名验证失败');
        }
    }

    private static function sign(string $keyPath, string $message): string
    {
        $privateKey = openssl_pkey_get_private((string)file_get_contents($keyPath));
        if ($privateKey === false) {
            throw new BusinessException('商户私钥无效');
        }

        openssl_sign($message, $raw, $privateKey, 'sha256WithRSAEncryption');

        return base64_encode($raw);
    }

    /**
     * @return array<string, string>
     */
    private static function config(): array
    {
        $config = [
            'appid'      => (string)config('wechat.appid', ''),
            'mch_id'     => (string)config('wechat.pay.mch_id', ''),
            'serial_no'  => (string)config('wechat.pay.serial_no', ''),
            'api_v3_key' => (string)config('wechat.pay.api_v3_key', ''),
            'cert_path'  => (string)config('wechat.pay.cert_path', ''),
            'key_path'   => (string)config('wechat.pay.key_path', ''),
            'notify_url' => (string)config('wechat.pay.notify_url', ''),
        ];

        foreach (['appid', 'mch_id', 'serial_no', 'api_v3_key', 'notify_url'] as $key) {
            if ($config[$key] === '') {
                throw new BusinessException('微信支付未配置完整，请联系管理员');
            }
        }
        if ($config['key_path'] === '' || !is_file($config['key_path'])) {
            throw new BusinessException('商户私钥文件不存在，请联系管理员');
        }

        return $config;
    }
}
