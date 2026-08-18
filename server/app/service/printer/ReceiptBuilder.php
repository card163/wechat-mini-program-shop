<?php

declare(strict_types=1);

namespace app\service\printer;

use app\model\Order;
use app\service\SettingService;

/**
 * 生成与厂商无关的小票行内容，由各 Adapter 自行渲染成对应标签/格式
 */
final class ReceiptBuilder
{
    /**
     * @return array<int, array{text?: string, align?: string, bold?: bool, divider?: bool}>
     */
    public static function forOrder(Order $order): array
    {
        $lines    = [];
        $shopName = self::clean(SettingService::get('base', 'shop_name', ''));
        if ($shopName !== '') {
            $lines[] = ['text' => $shopName, 'align' => 'center', 'bold' => true];
        }
        $lines[] = ['text' => '来单提醒', 'align' => 'center', 'bold' => true];
        $lines[] = ['divider' => true];
        $lines[] = ['text' => '订单号：' . $order->order_no];
        $lines[] = ['text' => '桌　号：' . self::clean((string)$order->table_name)];
        $lines[] = ['text' => '时　间：' . (string)($order->paid_at ?: $order->created_at)];
        $lines[] = ['divider' => true];

        foreach ($order->items as $item) {
            $lines[] = ['text' => sprintf('%s x%d', self::clean((string)$item->goods_name), (int)$item->quantity)];
        }

        $lines[] = ['divider' => true];
        $lines[] = ['text' => '合计：￥' . number_format(((int)$order->pay_amount) / 100, 2), 'bold' => true];

        $remark = trim((string)$order->remark);
        if ($remark !== '') {
            $lines[] = ['text' => '备注：' . self::clean($remark)];
        }

        return $lines;
    }

    /**
     * @return array<int, array{text?: string, align?: string, bold?: bool, divider?: bool}>
     */
    public static function forTest(): array
    {
        return [
            ['text' => '打印机测试', 'align' => 'center', 'bold' => true],
            ['divider' => true],
            ['text' => '这是一张测试小票，收到即代表配置成功'],
            ['text' => '时间：' . date('Y-m-d H:i:s')],
        ];
    }

    /**
     * 剥离可能破坏厂商小票标签指令的字符，用户可控文本（备注/桌号/商品名）必须经过此处理
     */
    private static function clean(string $text): string
    {
        return str_replace(['<', '>'], '', $text);
    }
}
