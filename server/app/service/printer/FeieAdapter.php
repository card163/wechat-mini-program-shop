<?php

declare(strict_types=1);

namespace app\service\printer;

use app\model\Printer;

/**
 * 飞鹅云打印开放平台
 * 接口文档：https://help.feieyun.com/document.php?doc=1（Open_printMsg）
 * 签名规则：sig = sha1(user + ukey + stime)
 */
final class FeieAdapter extends AbstractTagAdapter
{
    private const string API_URL_CN  = 'https://api.feieyun.cn/Api/Open/';
    private const string API_URL_SEA = 'https://api.jp.feieyun.com/Api/Open/';

    public function print(Printer $printer, array $lines): array
    {
        $stime  = (string)time();
        $times  = max(1, (int)$printer->voice_times ?: (int)$printer->copies ?: 1);
        $params = [
            'user'    => (string)$printer->account,
            'stime'   => $stime,
            'sig'     => sha1($printer->account . $printer->secret_key . $stime),
            'apiname' => 'Open_printMsg',
            'sn'      => (string)$printer->sn,
            'content' => $this->render($lines),
            'times'   => $times,
        ];

        $url      = (int)$printer->area === Printer::AREA_SEA ? self::API_URL_SEA : self::API_URL_CN;
        $response = $this->httpPost($url, $params);

        return [
            'success'  => (int)($response['ret'] ?? -1) === 0,
            'third_no' => (string)($response['data'] ?? ''),
            'message'  => (string)($response['msg'] ?? '未知错误'),
        ];
    }
}
