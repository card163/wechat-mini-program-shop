<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\Goods;
use app\model\Member;
use app\model\MemberBalanceLog;
use app\model\MemberDrinkCardBatch;
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
     */
    private static function applyFilters(\Illuminate\Database\Eloquent\Builder $query, array $filters): void
    {
        if (!empty($filters['order_no'])) {
            $query->where('order_no', (string)$filters['order_no']);
        }
        if (isset($filters['order_status']) && $filters['order_status'] !== '') {
            $query->where('order_status', (int)$filters['order_status']);
        }
        if (isset($filters['pay_status']) && $filters['pay_status'] !== '') {
            $query->where('pay_status', (int)$filters['pay_status']);
        }
        if (!empty($filters['pay_type'])) {
            $types = array_values(array_filter(array_map('intval', explode(',', (string)$filters['pay_type']))));
            if ($types !== []) {
                $query->whereIn('pay_type', $types);
            }
        }
        if (!empty($filters['table_id'])) {
            $query->where('table_id', (int)$filters['table_id']);
        }
        if (!empty($filters['member_id'])) {
            $query->where('member_id', (int)$filters['member_id']);
        }
        if (!empty($filters['phone'])) {
            $memberIds = Member::query()->where('phone', 'like', '%' . (string)$filters['phone'] . '%')->pluck('id');
            $query->whereIn('member_id', $memberIds);
        }
        // date_field=paid 时按支付时间筛选（用于从数据概览按支付渠道跳转，与概览统计口径保持一致），默认仍按下单时间
        $dateField = ((string)($filters['date_field'] ?? 'created')) === 'paid' ? 'paid_at' : 'created_at';
        if (!empty($filters['start_date'])) {
            $query->where($dateField, '>=', self::normalizeDate((string)$filters['start_date'], true));
        }
        if (!empty($filters['end_date'])) {
            $query->where($dateField, '<=', self::normalizeDate((string)$filters['end_date'], false));
        }
    }

    /**
     * 兼容纯日期(YYYY-MM-DD)与精确到分钟(YYYY-MM-DD HH:mm)两种入参，纯日期按当天首尾补全
     */
    private static function normalizeDate(string $value, bool $isStart): string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value . ($isStart ? ' 00:00:00' : ' 23:59:59');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) === 1) {
            return $value . ($isStart ? ':00' : ':59');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{list: array<int, array<string, mixed>>, total: int}
     */
    public static function paginate(array $filters, int $page, int $pageSize): array
    {
        $query = Order::query()->with('items');
        self::applyFilters($query, $filters);

        $total = (int)$query->count();
        $orders = $query->orderByDesc('id')
            ->forPage($page, $pageSize)
            ->get();

        $phoneMap = Member::query()
            ->whereIn('id', $orders->pluck('member_id')->unique()->all())
            ->pluck('phone', 'id');
        $list = $orders
            ->map(static fn(Order $order): array => self::format($order, (string)($phoneMap[$order->member_id] ?? '')))
            ->all();

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 统计当前筛选条件下的支付金额构成
     *
     * @param array<string, mixed> $filters
     * @return array{count: int, pay_wechat: int, pay_balance: int, pay_gift: int, pay_drink_card: int}
     */
    public static function summary(array $filters): array
    {
        $query = Order::query();
        self::applyFilters($query, $filters);

        $row = $query->selectRaw('count(*) as cnt, sum(pay_wechat) as sum_wechat, sum(pay_balance) as sum_balance, sum(pay_gift) as sum_gift, sum(pay_drink_card) as sum_drink_card')->first();

        return [
            'count'          => (int)($row?->cnt ?? 0),
            'pay_wechat'     => (int)($row?->sum_wechat ?? 0),
            'pay_balance'    => (int)($row?->sum_balance ?? 0),
            'pay_gift'       => (int)($row?->sum_gift ?? 0),
            'pay_drink_card' => (int)($row?->sum_drink_card ?? 0),
        ];
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

        $phone = (string)(Member::query()->where('id', $order->member_id)->value('phone') ?? '');

        return self::format($order, $phone);
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

            if ((int)$order->pay_drink_card > 0) {
                AccountService::grantDrinkCard(
                    $member,
                    (int)$order->pay_drink_card,
                    MemberDrinkCardBatch::SOURCE_REFUND,
                    (int)$order->id,
                    0,
                    (string)$order->order_no,
                    '订单退款退回饮品卡',
                    $operatorId
                );
            }

            if ((int)$order->gain_point > 0) {
                AccountService::changePoint(
                    $member,
                    -(int)$order->gain_point,
                    MemberPointLog::BIZ_REFUND_ROLLBACK,
                    (int)$order->id,
                    '订单退款回滚礼品卡',
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
                // 微信退款接口的 total 必须是当初该笔交易在微信侧实际收到的金额：
                // 纯微信支付时 pay_wechat===pay_amount；微信+酒水卡/饮品卡组合支付时微信只收了差额，
                // 传 pay_amount 会与微信侧记录的原始交易金额不符导致退款失败，故统一改用 pay_wechat
                WechatPayService::refund(
                    (string)$order->order_no,
                    'RF' . (string)$order->order_no,
                    (int)$order->pay_wechat,
                    (int)$order->pay_wechat
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
    public static function format(Order $order, string $memberPhone = ''): array
    {
        return [
            'id'           => (int)$order->id,
            'order_no'     => (string)$order->order_no,
            'daily_no'     => (int)$order->daily_no,
            'member_id'    => (int)$order->member_id,
            'member_phone' => $memberPhone,
            'table_name'   => (string)$order->table_name,
            'total_amount' => (int)$order->total_amount,
            'pay_amount'   => (int)$order->pay_amount,
            'pay_type'     => (int)$order->pay_type,
            'pay_balance'  => (int)$order->pay_balance,
            'pay_gift'     => (int)$order->pay_gift,
            'pay_drink_card' => (int)$order->pay_drink_card,
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
