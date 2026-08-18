<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\Goods;
use app\model\MemberBalanceLog;
use app\model\MemberGiftBatch;
use app\model\MemberPointLog;
use app\model\Order;
use app\model\OrderItem;
use app\support\Result;
use Illuminate\Database\Capsule\Manager as Db;

class AdminOrderService
{
    /**
     * @param array<string, mixed> $filters
     * @return array{list: array<int, array<string, mixed>>, total: int}
     */
    public static function paginate(array $filters, int $page, int $pageSize): array
    {
        $query = Order::query()->with('items');

        if (!empty($filters['order_no'])) {
            $query->where('order_no', (string)$filters['order_no']);
        }
        if (isset($filters['order_status']) && $filters['order_status'] !== '') {
            $query->where('order_status', (int)$filters['order_status']);
        }
        if (!empty($filters['table_id'])) {
            $query->where('table_id', (int)$filters['table_id']);
        }
        if (!empty($filters['member_id'])) {
            $query->where('member_id', (int)$filters['member_id']);
        }
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date'] . ' 00:00:00');
        }
        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date'] . ' 23:59:59');
        }

        $total = (int)$query->count();
        $list  = $query->orderByDesc('id')
            ->forPage($page, $pageSize)
            ->get()
            ->map(static fn(Order $order): array => self::format($order))
            ->all();

        return ['list' => $list, 'total' => $total];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(int $orderId): array
    {
        $order = Order::query()->with('items')->find($orderId);
        if ($order === null) {
            throw new BusinessException('订单不存在', Result::NOT_FOUND);
        }

        return self::format($order);
    }

    public static function finish(int $orderId): void
    {
        $order = Order::query()->find($orderId);
        if ($order === null) {
            throw new BusinessException('订单不存在', Result::NOT_FOUND);
        }
        if ((int)$order->pay_status !== Order::PAY_STATUS_PAID) {
            throw new BusinessException('订单未支付，无法完成');
        }
        if ((int)$order->order_status === Order::STATUS_FINISHED) {
            throw new BusinessException('订单已完成');
        }

        $order->order_status = Order::STATUS_FINISHED;
        $order->finished_at  = date('Y-m-d H:i:s');
        $order->save();
    }

    /**
     * 补打印：向全部已启用打印机重新推送一次
     *
     * @return array{success: int, failed: int}
     */
    public static function print(int $orderId): array
    {
        return PrinterService::reprint($orderId);
    }

    /**
     * 退款：赠金与本金原路退回，微信支付部分调用微信退款接口
     */
    public static function refund(int $orderId, string $remark, int $operatorId): void
    {
        Db::connection()->transaction(static function () use ($orderId, $remark, $operatorId): void {
            $order = Order::query()->with('items')->lockForUpdate()->find($orderId);
            if ($order === null) {
                throw new BusinessException('订单不存在', Result::NOT_FOUND);
            }
            if ((int)$order->pay_status !== Order::PAY_STATUS_PAID) {
                throw new BusinessException('该订单未支付，无法退款');
            }

            $member = AccountService::lockMember((int)$order->member_id);

            if ((int)$order->pay_gift > 0) {
                AccountService::grantGift(
                    $member,
                    (int)$order->pay_gift,
                    MemberGiftBatch::SOURCE_REFUND,
                    (int)$order->id,
                    SettingService::int('point', 'gift_default_days', 0),
                    (string)$order->order_no,
                    '订单退款退回赠金',
                    $operatorId
                );
            }

            if ((int)$order->pay_balance > 0) {
                AccountService::increaseBalance(
                    $member,
                    (int)$order->pay_balance,
                    MemberBalanceLog::BIZ_REFUND,
                    (int)$order->id,
                    (string)$order->order_no,
                    '订单退款',
                    $operatorId
                );
            }

            if ((int)$order->gain_point > 0) {
                AccountService::changePoint(
                    $member,
                    -(int)$order->gain_point,
                    MemberPointLog::BIZ_REFUND_ROLLBACK,
                    (int)$order->id,
                    '订单退款回滚记分牌',
                    $operatorId
                );
            }

            $member->total_consume = max(0, (int)$member->total_consume - (int)$order->pay_amount);
            $member->save();

            foreach ($order->items as $item) {
                Goods::query()
                    ->whereKey((int)$item->goods_id)
                    ->where('stock', '!=', Goods::STOCK_UNLIMITED)
                    ->increment('stock', (int)$item->quantity);
                Goods::query()
                    ->whereKey((int)$item->goods_id)
                    ->where('sales', '>=', (int)$item->quantity)
                    ->decrement('sales', (int)$item->quantity);
            }

            if ((int)$order->pay_wechat > 0) {
                WechatPayService::refund(
                    (string)$order->order_no,
                    'RF' . (string)$order->order_no,
                    (int)$order->pay_wechat,
                    (int)$order->pay_amount
                );
            }

            $order->pay_status   = Order::PAY_STATUS_REFUNDED;
            $order->order_status = Order::STATUS_CANCELLED;
            $order->cancelled_at = date('Y-m-d H:i:s');
            $order->remark       = trim((string)$order->remark . ' [退款]' . $remark);
            $order->save();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function format(Order $order): array
    {
        return [
            'id'           => (int)$order->id,
            'order_no'     => (string)$order->order_no,
            'member_id'    => (int)$order->member_id,
            'table_name'   => (string)$order->table_name,
            'total_amount' => (int)$order->total_amount,
            'pay_amount'   => (int)$order->pay_amount,
            'pay_type'     => (int)$order->pay_type,
            'pay_balance'  => (int)$order->pay_balance,
            'pay_gift'     => (int)$order->pay_gift,
            'pay_wechat'   => (int)$order->pay_wechat,
            'pay_status'   => (int)$order->pay_status,
            'order_status' => (int)$order->order_status,
            'gain_point'   => (int)$order->gain_point,
            'remark'       => (string)$order->remark,
            'created_at'   => (string)$order->created_at,
            'paid_at'      => $order->paid_at === null ? null : (string)$order->paid_at,
            'finished_at'  => $order->finished_at === null ? null : (string)$order->finished_at,
            'items'        => $order->items->map(static fn(OrderItem $item): array => [
                'goods_id'   => (int)$item->goods_id,
                'goods_name' => (string)$item->goods_name,
                'price'      => (int)$item->price,
                'quantity'   => (int)$item->quantity,
                'subtotal'   => (int)$item->subtotal,
            ])->all(),
        ];
    }
}
