<?php

declare(strict_types=1);

namespace app\service\printer;

use app\model\Printer;
use RuntimeException;
use Throwable;

/**
 * 商米云打印开放平台
 *
 * ⚠️ 商米开放平台鉴权与打印接口的具体 endpoint/字段以其开放平台（https://open.sunmi.com）最新文档为准，
 * 本实现按 AppId/AppSecret 换取 access_token 后调用打印接口的通用流程编写，
 * 上线前必须用商米提供的真实测试打印机核实以下常量与响应字段，如有出入以官方文档为准调整。
 */
final class SunmiAdapter implements PrinterAdapterInterface
{
    private const string TOKEN_URL = 'https://openapi.sunmi.com/oauth/token';
    private const string PRINT_URL = 'https://openapi.sunmi.com/v1/printer/text/print';

    public function print(Printer $printer, array $lines): array
    {
        try {
            $token = $this->fetchToken($printer);
        } catch (Throwable $e) {
            return ['success' => false, 'third_no' => '', 'message' => '获取商米access_token失败：' . $e->getMessage()];
        }

        $payload = json_encode([
            'sn'     => (string)$printer->sn,
            'copies' => max(1, (int)$printer->copies ?: 1),
            'data'   => $this->renderPlain($lines),
        ], JSON_UNESCAPED_UNICODE);

        $response = $this->httpPostJson(self::PRINT_URL, (string)$payload, $token);

        return [
            'success'  => (int)($response['code'] ?? -1) === 0,
            'third_no' => (string)($response['data']['id'] ?? ''),
            'message'  => (string)($response['message'] ?? $response['msg'] ?? '未知错误'),
        ];
    }

    private function fetchToken(Printer $printer): string
    {
        $payload = json_encode([
            'appId'     => (string)$printer->account,
            'appSecret' => (string)$printer->secret_key,
            'grantType' => 'client_credentials',
        ], JSON_UNESCAPED_UNICODE);

        $response = $this->httpPostJson(self::TOKEN_URL, (string)$payload, null);
        $token    = (string)($response['data']['accessToken'] ?? '');
        if ($token === '') {
            throw new RuntimeException((string)($response['message'] ?? '返回内容异常'));
        }

        return $token;
    }

    /**
     * @param array<int, array{text?: string, align?: string, bold?: bool, divider?: bool}> $lines
     */
    private function renderPlain(array $lines): string
    {
        $parts = [];
        foreach ($lines as $line) {
            if (!empty($line['divider'])) {
                $parts[] = str_repeat('-', 32);
                continue;
            }
            $parts[] = (string)($line['text'] ?? '');
        }

        return implode("\n", $parts) . "\n\n\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function httpPostJson(string $url, string $payload, ?string $token): array
    {
        $headers = ['Content-Type: application/json'];
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 8,
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException($error !== '' ? $error : '商米打印接口请求失败');
        }
        curl_close($ch);

        $data = json_decode((string)$body, true);

        return is_array($data) ? $data : [];
    }
}
