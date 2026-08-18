<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\Order;
use app\model\PrintLog;
use app\model\Printer;
use app\service\printer\FeieAdapter;
use app\service\printer\PrinterAdapterInterface;
use app\service\printer\ReceiptBuilder;
use app\service\printer\SunmiAdapter;
use app\service\printer\XpyunAdapter;
use app\support\Result;
use support\Log;
use Throwable;

/**
 * 接单打印机调度：向全部启用的打印机推送小票
 */
class PrinterService
{
    /**
     * 支付成功后自动打印，任何异常都不外抛，避免影响支付主流程
     */
    public static function autoPrint(int $orderId): void
    {
        try {
            $order = Order::query()->with('items')->find($orderId);
            if ($order === null) {
                return;
            }
            self::dispatchAll($order);
        } catch (Throwable $e) {
            Log::error("订单[$orderId]自动打印异常：" . $e->getMessage());
        }
    }

    /**
     * 手动补打印（后台触发），失败会抛出业务异常
     *
     * @return array{success: int, failed: int}
     */
    public static function reprint(int $orderId): array
    {
        $order = Order::query()->with('items')->find($orderId);
        if ($order === null) {
            throw new BusinessException('订单不存在', Result::NOT_FOUND);
        }
        if ((int)$order->pay_status !== Order::PAY_STATUS_PAID) {
            throw new BusinessException('订单未支付，无法打印');
        }

        return self::dispatchAll($order);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public static function testPrint(Printer $printer): array
    {
        return self::dispatch($printer, null, ReceiptBuilder::forTest());
    }

    /**
     * @return array{success: int, failed: int}
     */
    private static function dispatchAll(Order $order): array
    {
        $printers = Printer::query()->where('status', Printer::STATUS_ON)->orderBy('sort')->get();
        $lines    = ReceiptBuilder::forOrder($order);

        $success = 0;
        $failed  = 0;
        foreach ($printers as $printer) {
            $result = self::dispatch($printer, $order, $lines);
            $result['success'] ? $success++ : $failed++;
        }

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * @param array<int, array{text?: string, align?: string, bold?: bool, divider?: bool}> $lines
     * @return array{success: bool, message: string}
     */
    private static function dispatch(Printer $printer, ?Order $order, array $lines): array
    {
        $log             = new PrintLog();
        $log->printer_id = (int)$printer->id;
        $log->order_id   = $order !== null ? (int)$order->id : 0;
        $log->order_no   = $order !== null ? (string)$order->order_no : '';
        $log->vendor     = (int)$printer->vendor;
        $log->content    = json_encode($lines, JSON_UNESCAPED_UNICODE) ?: '';
        $log->status     = PrintLog::STATUS_PENDING;

        try {
            $result           = self::adapter((int)$printer->vendor)->print($printer, $lines);
            $log->status      = $result['success'] ? PrintLog::STATUS_SUCCESS : PrintLog::STATUS_FAILED;
            $log->third_no    = (string)($result['third_no'] ?? '');
            $log->fail_reason = $result['success'] ? '' : mb_substr((string)($result['message'] ?? '未知错误'), 0, 255);
        } catch (Throwable $e) {
            $log->status      = PrintLog::STATUS_FAILED;
            $log->fail_reason = mb_substr($e->getMessage(), 0, 255);
        }
        $log->save();

        if ((int)$log->status === PrintLog::STATUS_FAILED) {
            Log::error("打印机[{$printer->name}]打印失败：{$log->fail_reason}");
        }

        return ['success' => (int)$log->status === PrintLog::STATUS_SUCCESS, 'message' => (string)$log->fail_reason];
    }

    private static function adapter(int $vendor): PrinterAdapterInterface
    {
        return match ($vendor) {
            Printer::VENDOR_FEIE  => new FeieAdapter(),
            Printer::VENDOR_XPYUN => new XpyunAdapter(),
            Printer::VENDOR_SUNMI => new SunmiAdapter(),
            default               => throw new BusinessException('不支持的打印机厂商'),
        };
    }
}
