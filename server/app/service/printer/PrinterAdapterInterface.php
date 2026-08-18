<?php

declare(strict_types=1);

namespace app\service\printer;

use app\model\Printer;

/**
 * 打印机厂商适配器统一接口
 */
interface PrinterAdapterInterface
{
    /**
     * @param array<int, array{text?: string, align?: string, bold?: bool, divider?: bool}> $lines
     * @return array{success: bool, third_no: string, message: string}
     */
    public function print(Printer $printer, array $lines): array;
}
