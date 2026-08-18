<?php

declare(strict_types=1);

namespace app\service\printer;

use app\model\Printer;

/**
 * 芯烨云打印开放平台
 * 接口文档：http://www.xpyun.net/doc（xprinter/print）
 * 签名规则：sign = strtoupper(md5(user + ukey + timestamp))
 */
final class XpyunAdapter extends AbstractTagAdapter
{
    private const string API_URL = 'http://open.xpyun.net/api/openapi/xprinter/print';

    public function print(Printer $printer, array $lines): array
    {
        $timestamp = (string)time();
        $params    = [
            'user'      => (string)$printer->account,
            'timestamp' => $timestamp,
            'sign'      => strtoupper(md5($printer->account . $printer->secret_key . $timestamp)),
            'sn'        => (string)$printer->sn,
            'content'   => $this->render($lines),
            'times'     => max(1, (int)$printer->copies ?: 1),
        ];

        $response = $this->httpPost(self::API_URL, $params);

        return [
            'success'  => (int)($response['code'] ?? -1) === 0,
            'third_no' => (string)($response['data']['no'] ?? ''),
            'message'  => (string)($response['msg'] ?? '未知错误'),
        ];
    }
}
