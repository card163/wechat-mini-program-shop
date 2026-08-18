<?php

declare(strict_types=1);

namespace app\service\printer;

use RuntimeException;

/**
 * 飞鹅云打印 / 芯烨云打印共用：两家均支持 <BR> 换行、<BOLD></BOLD> 加粗、<CB></CB> 居中加粗等标签指令
 */
abstract class AbstractTagAdapter implements PrinterAdapterInterface
{
    /**
     * @param array<int, array{text?: string, align?: string, bold?: bool, divider?: bool}> $lines
     */
    protected function render(array $lines): string
    {
        $parts = [];
        foreach ($lines as $line) {
            if (!empty($line['divider'])) {
                $parts[] = str_repeat('-', 32);
                continue;
            }

            $text = (string)($line['text'] ?? '');
            if (($line['bold'] ?? false) === true) {
                $text = "<BOLD>$text</BOLD>";
            }
            if (($line['align'] ?? '') === 'center') {
                $text = "<CB>$text</CB>";
            }
            $parts[] = $text;
        }

        return implode('<BR>', $parts) . '<BR><BR><BR>';
    }

    /**
     * @param array<string, string|int> $params
     * @return array<string, mixed>
     */
    protected function httpPost(string $url, array $params): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 8,
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException($error !== '' ? $error : '打印机接口请求失败');
        }
        curl_close($ch);

        $data = json_decode((string)$body, true);

        return is_array($data) ? $data : [];
    }
}
