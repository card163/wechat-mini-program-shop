<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\DiningTable;
use app\model\Goods;
use app\model\Member;
use app\model\MemberBalanceLog;
use app\model\MemberPointLog;
use app\model\Order;
use app\model\OrderItem;
use app\support\Result;
use app\support\Sn;
use Illuminate\Database\Capsule\Manager as Db;

class OrderService
{
    /**
     * 结算预览：金额与抵扣方案全部由服务端计算
     *
     * @param array<int, array{goods_id: int|string, quantity: int|string}> $items
     * @return array<string, mixed>
     */
    public static function preview(int $memberId, array $items): array
    {
        $member  = Member::query()->findOrFail($memberId);
        $checked = self::resolveItems($items);

        $plan = self::planPayment(
            (int)$member->balance,
            (int)$member->gift_balance,
            $checked['total_amount'],
            $checked['gift_payable_amount']
        );

        return [
            'items'               => $checked['items'],
            'total_amount'        => $checked['total_amount'],
            'pay_amount'          => $checked['total_amount'],
            'gift_payable_amount' => $checked['gift_payable_amount'],
            'balance'             => (int)$member->balance,
            'gift_balance'        => (int)$member->gift_balance,
            'balance_enough'      => $plan['enough'],
            'plan'                => [
                'pay_gift'    => $plan['pay_gift'],
                'pay_balance' => $plan['pay_balance'],
            ],
        ];
    }

    /**
     * 创建订单
     *
     * @param array<int, array{goods_id: int|string, quantity: int|string}> $items
     * @return array<string, mixed>
     */
    public static function create(int $memberId, array $items, int $tableId, int $payType, string $remark): array
    {
        if (!in_array($payType, [Order::PAY_TYPE_WECHAT, Order::PAY_TYPE_BALANCE], true)) {
            throw new BusinessException('支付方式不正确');
        }

        $table = DiningTable::query()->where('status', DiningTable::STATUS_ON)->find($tableId);
        if ($table === null) {
            throw new BusinessException('桌号不存在');
        }

        $orderId = Db::connection()->transaction(static function () use ($memberId, $items, $table, $payType, $remark): int {
            $member  = AccountService::lockMember($memberId);
            $checked = self::resolveItems($items, true);

            $order = new Order();
            $order->order_no     = Sn::make(Sn::ORDER);
            $order->member_id    = $memberId;
            $order->table_id     = (int)$table->id;
            $order->table_name   = (string)$table->name;
            $order->total_amount = $checked['total_amount'];
            $order->pay_amount   = $checked['total_amount'];
            $order->pay_type     = $payType;
            $order->pay_status   = Order::PAY_STATUS_UNPAID;
            $order->order_status = Order::STATUS_UNPAID;
            $order->remark       = mb_substr($remark, 0, 200);
            $order->save();

            foreach ($checked['items'] as $item) {
                $orderItem = new OrderItem();
                $orderItem->order_id    = (int)$order->id;
                $orderItem->goods_id    = $item['goods_id'];
                $orderItem->goods_name  = $item['goods_name'];
                $orderItem->goods_cover = $item['goods_cover'];
                $orderItem->price       = $item['price'];
                $orderItem->quantity    = $item['quantity'];
                $orderItem->subtotal    = $item['subtotal'];
                $orderItem->save();
            }

            self::reduceStock($checked['items']);

            if ($payType === Order::PAY_TYPE_BALANCE) {
                self::payByBalance($member, $order, $checked['gift_payable_amount']);
            }

            return (int)$order->id;
        });

        return self::payResult($memberId, $orderId);
    }

    /**
     * 待支付订单重新发起支付
     *
     * @return array<string, mixed>
     */
    public static function pay(int $memberId, int $orderId, int $payType): array
    {
        if (!in_array($payType, [Order::PAY_TYPE_WECHAT, Order::PAY_TYPE_BALANCE], true)) {
            throw new BusinessException('支付方式不正确');
        }

        Db::connection()->transaction(static function () use ($memberId, $orderId, $payType): void {
            $member = AccountService::lockMember($memberId);
            $order  = self::lockOrder($memberId, $orderId);

            self::assertPayable($order);

            $order->pay_type = $payType;
            $order->save();

            if ($payType === Order::PAY_TYPE_BALANCE) {
                self::payByBalance($member, $order, self::giftPayableAmountOfOrder($order));
            }
        });

        return self::payResult($memberId, $orderId);
    }

    public static function cancel(int $memberId, int $orderId): void
    {
        Db::connection()->transaction(static function () use ($memberId, $orderId): void {
            $order = self::lockOrder($memberId, $orderId);

            if ((int)$order->pay_status === Order::PAY_STATUS_PAID) {
                throw new BusinessException('订单已支付，无法取消');
            }
            if ((int)$order->order_status !== Order::STATUS_UNPAID) {
                throw new BusinessException('订单状态已变更，请刷新');
            }

            $order->order_status = Order::STATUS_CANCELLED;
            $order->cancelled_at = date('Y-m-d H:i:s');
            $order->save();

            self::restoreStock($order);
        });
    }

    /**
     * 微信支付成功后入账，必须幂等
     */
    public static function markPaidByWechat(string $orderNo, string $transactionId, int $paidAmount): void
    {
        Db::connection()->transaction(static function () use ($orderNo, $transactionId, $paidAmount): void {
            $order = Order::query()->where('order_no', $orderNo)->lockForUpdate()->first();
            if ($order === null) {
                throw new BusinessException('订单不存在', Result::NOT_FOUND);
            }
            if ((int)$order->pay_status === Order::PAY_STATUS_PAID) {
                return;
            }
            if ($paidAmount !== (int)$order->pay_amount) {
                throw new BusinessException('支付金额与订单不一致');
            }

            $order->transaction_id = $transactionId;
            $order->pay_wechat     = $paidAmount;
            $order->pay_status     = Order::PAY_STATUS_PAID;
            $order->order_status   = Order::STATUS_PAID;
            $order->paid_at        = date('Y-m-d H:i:s');
            $order->save();

            $member = AccountService::lockMember((int)$order->member_id);
            self::afterPaid($member, $order);
        });
    }

    /**
     * @return array{list: array<int, array<string, mixed>>, total: int}
     */
    public static function paginate(int $memberId, ?int $status, int $page, int $pageSize): array
    {
        $query = Order::query()->where('member_id', $memberId);
        if ($status !== null) {
            $query->where('order_status', $status);
        }

        $total = (int)$query->count();
        $list  = $query->with('items')
            ->orderByDesc('id')
            ->forPage($page, $pageSize)
            ->get()
            ->map(static fn(Order $order): array => self::format($order))
            ->all();

        return ['list' => $list, 'total' => $total];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(int $memberId, int $orderId): array
    {
        $order = Order::query()->with('items')->where('member_id', $memberId)->find($orderId);
        if ($order === null) {
            throw new BusinessException('订单不存在', Result::NOT_FOUND);
        }

        return self::format($order) + [
            'pay_balance' => (int)$order->pay_balance,
            'pay_gift'    => (int)$order->pay_gift,
            'pay_wechat'  => (int)$order->pay_wechat,
        ];
    }

    /**
     * 关闭超时未支付订单并回滚库存
     */
    public static function closeExpired(): int
    {
        $minutes = max(1, SettingService::int('order', 'auto_cancel_minutes', 15));
        $deadline = date('Y-m-d H:i:s', time() - $minutes * 60);

        $orderIds = Order::query()
            ->where('order_status', Order::STATUS_UNPAID)
            ->where('created_at', '<=', $deadline)
            ->pluck('id')
            ->all();

        $closed = 0;
        foreach ($orderIds as $orderId) {
            $closed += Db::connection()->transaction(static function () use ($orderId): int {
                $order = Order::query()->lockForUpdate()->find($orderId);
                if ($order === null || (int)$order->order_status !== Order::STATUS_UNPAID) {
                    return 0;
                }

                $order->order_status = Order::STATUS_CANCELLED;
                $order->cancelled_at = date('Y-m-d H:i:s');
                $order->save();

                self::restoreStock($order);

                return 1;
            });
        }

        return $closed;
    }

    /**
     * 余额支付：优先扣赠金，不足部分扣本金
     */
    private static function payByBalance(Member $member, Order $order, int $giftPayableAmount): void
    {
        $plan = self::planPayment((int)$member->balance, (int)$member->gift_balance, (int)$order->pay_amount, $giftPayableAmount);
        if (!$plan['enough']) {
            throw new BusinessException('余额不足，请先充值');
        }

        if ($plan['pay_gift'] > 0) {
            AccountService::decreaseGift($member, $plan['pay_gift'], MemberBalanceLog::BIZ_CONSUME, (int)$order->id, (string)$order->order_no, '点单消费');
        }
        if ($plan['pay_balance'] > 0) {
            AccountService::decreaseBalance($member, $plan['pay_balance'], MemberBalanceLog::BIZ_CONSUME, (int)$order->id, (string)$order->order_no, '点单消费');
        }

        $order->pay_balance  = $plan['pay_balance'];
        $order->pay_gift     = $plan['pay_gift'];
        $order->pay_status   = Order::PAY_STATUS_PAID;
        $order->order_status = Order::STATUS_PAID;
        $order->paid_at      = date('Y-m-d H:i:s');
        $order->save();

        self::afterPaid($member, $order);
    }

    /**
     * 支付成功后的通用处理：累计消费、赠送记分牌、增加销量
     */
    private static function afterPaid(Member $member, Order $order): void
    {
        $member->total_consume = (int)$member->total_consume + (int)$order->pay_amount;
        $member->save();

        $rate = SettingService::int('order', 'consume_point_rate', 0);
        if ($rate > 0) {
            $point = intdiv((int)$order->pay_amount, 100) * $rate;
            if ($point > 0) {
                $order->gain_point = $point;
                $order->save();
                AccountService::changePoint($member, $point, MemberPointLog::BIZ_CONSUME_GAIN, (int)$order->id, '点单消费获得');
            }
        }

        foreach ($order->items as $item) {
            Goods::query()->whereKey((int)$item->goods_id)->increment('sales', (int)$item->quantity);
        }
    }

    /**
     * 计算支付方案：赠金只能覆盖允许赠金支付的商品金额
     *
     * @return array{pay_gift: int, pay_balance: int, enough: bool}
     */
    private static function planPayment(int $balance, int $giftBalance, int $payAmount, int $giftPayableAmount): array
    {
        $giftEnabled = SettingService::int('order', 'gift_pay_enabled', 1) === 1;
        $payGift     = $giftEnabled ? min($giftBalance, $giftPayableAmount, $payAmount) : 0;
        $payBalance  = $payAmount - $payGift;

        return [
            'pay_gift'    => $payGift,
            'pay_balance' => $payBalance,
            'enough'      => $balance >= $payBalance,
        ];
    }

    /**
     * 校验商品并计算金额，$lock 为 true 时对商品行加锁
     *
     * @param array<int, array{goods_id: int|string, quantity: int|string}> $items
     * @return array{items: array<int, array<string, mixed>>, total_amount: int, gift_payable_amount: int}
     */
    private static function resolveItems(array $items, bool $lock = false): array
    {
        if ($items === []) {
            throw new BusinessException('请先选择商品');
        }

        $quantities = [];
        foreach ($items as $item) {
            $goodsId  = (int)($item['goods_id'] ?? 0);
            $quantity = (int)($item['quantity'] ?? 0);
            if ($goodsId <= 0 || $quantity <= 0 || $quantity > 99) {
                throw new BusinessException('商品数量不正确');
            }
            $quantities[$goodsId] = ($quantities[$goodsId] ?? 0) + $quantity;
        }

        $query = Goods::query()->whereIn('id', array_keys($quantities));
        if ($lock) {
            $query->lockForUpdate();
        }
        $goodsList = $query->get()->keyBy('id');

        $resolved          = [];
        $totalAmount       = 0;
        $giftPayableAmount = 0;

        foreach ($quantities as $goodsId => $quantity) {
            /** @var Goods|null $goods */
            $goods = $goodsList->get($goodsId);
            if ($goods === null || (int)$goods->status !== Goods::STATUS_ON) {
                throw new BusinessException('商品已下架，请重新选择');
            }
            if ((int)$goods->stock !== Goods::STOCK_UNLIMITED && (int)$goods->stock < $quantity) {
                throw new BusinessException("「{$goods->name}」库存不足");
            }

            $subtotal    = (int)$goods->price * $quantity;
            $totalAmount += $subtotal;
            if ((int)$goods->gift_payable === 1) {
                $giftPayableAmount += $subtotal;
            }

            $resolved[] = [
                'goods_id'     => (int)$goods->id,
                'goods_name'   => (string)$goods->name,
                'goods_cover'  => (string)$goods->cover,
                'price'        => (int)$goods->price,
                'quantity'     => $quantity,
                'subtotal'     => $subtotal,
                'gift_payable' => (int)$goods->gift_payable,
            ];
        }

        if ($totalAmount <= 0) {
            throw new BusinessException('订单金额不正确');
        }

        return [
            'items'               => $resolved,
            'total_amount'        => $totalAmount,
            'gift_payable_amount' => $giftPayableAmount,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private static function reduceStock(array $items): void
    {
        foreach ($items as $item) {
            $affected = Goods::query()
                ->whereKey($item['goods_id'])
                ->where('stock', '!=', Goods::STOCK_UNLIMITED)
                ->where('stock', '>=', $item['quantity'])
                ->decrement('stock', $item['quantity']);

            if ($affected === 0) {
                $goods = Goods::query()->find($item['goods_id']);
                if ($goods !== null && (int)$goods->stock !== Goods::STOCK_UNLIMITED) {
                    throw new BusinessException("「{$item['goods_name']}」库存不足");
                }
            }
        }
    }

    private static function restoreStock(Order $order): void
    {
        foreach ($order->items as $item) {
            Goods::query()
                ->whereKey((int)$item->goods_id)
                ->where('stock', '!=', Goods::STOCK_UNLIMITED)
                ->increment('stock', (int)$item->quantity);
        }
    }

    private static function lockOrder(int $memberId, int $orderId): Order
    {
        $order = Order::query()->where('member_id', $memberId)->lockForUpdate()->find($orderId);
        if ($order === null) {
            throw new BusinessException('订单不存在', Result::NOT_FOUND);
        }

        return $order;
    }

    private static function assertPayable(Order $order): void
    {
        if ((int)$order->pay_status === Order::PAY_STATUS_PAID) {
            throw new BusinessException('订单已支付，请勿重复操作');
        }
        if ((int)$order->order_status === Order::STATUS_CANCELLED) {
            throw new BusinessException('订单已取消');
        }
    }

    private static function giftPayableAmountOfOrder(Order $order): int
    {
        $amount = 0;
        foreach ($order->items as $item) {
            $goods = Goods::query()->find((int)$item->goods_id);
            if ($goods !== null && (int)$goods->gift_payable === 1) {
                $amount += (int)$item->subtotal;
            }
        }

        return $amount;
    }

    /**
     * @return array<string, mixed>
     */
    private static function payResult(int $memberId, int $orderId): array
    {
        $order = Order::query()->where('member_id', $memberId)->findOrFail($orderId);

        $payParams = null;
        if ((int)$order->pay_type === Order::PAY_TYPE_WECHAT && (int)$order->pay_status === Order::PAY_STATUS_UNPAID) {
            $member    = Member::query()->findOrFail($memberId);
            $payParams = WechatPayService::jsapiPay(
                (string)$order->order_no,
                (int)$order->pay_amount,
                '点单-' . (string)$order->table_name,
                (string)$member->openid
            );
        }

        return [
            'order_id'   => (int)$order->id,
            'order_no'   => (string)$order->order_no,
            'pay_type'   => (int)$order->pay_type,
            'pay_amount' => (int)$order->pay_amount,
            'pay_status' => (int)$order->pay_status,
            'gain_point' => (int)$order->gain_point,
            'pay_params' => $payParams,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function format(Order $order): array
    {
        return [
            'id'                => (int)$order->id,
            'order_no'          => (string)$order->order_no,
            'table_name'        => (string)$order->table_name,
            'total_amount'      => (int)$order->total_amount,
            'pay_amount'        => (int)$order->pay_amount,
            'pay_type'          => (int)$order->pay_type,
            'pay_status'        => (int)$order->pay_status,
            'order_status'      => (int)$order->order_status,
            'order_status_text' => self::statusText((int)$order->order_status),
            'gain_point'        => (int)$order->gain_point,
            'remark'            => (string)$order->remark,
            'created_at'        => (string)$order->created_at,
            'paid_at'           => $order->paid_at === null ? null : (string)$order->paid_at,
            'items'             => $order->items->map(static fn(OrderItem $item): array => [
                'goods_id'    => (int)$item->goods_id,
                'goods_name'  => (string)$item->goods_name,
                'goods_cover' => (string)$item->goods_cover,
                'price'       => (int)$item->price,
                'quantity'    => (int)$item->quantity,
                'subtotal'    => (int)$item->subtotal,
            ])->all(),
        ];
    }

    private static function statusText(int $status): string
    {
        return match ($status) {
            Order::STATUS_UNPAID    => '待支付',
            Order::STATUS_PAID      => '已支付',
            Order::STATUS_FINISHED  => '已完成',
            Order::STATUS_CANCELLED => '已取消',
            default                 => '未知',
        };
    }
}
