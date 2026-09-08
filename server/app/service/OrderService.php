<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\DiningTable;
use app\model\Goods;
use app\model\Member;
use app\model\MemberBalanceLog;
use app\model\MemberDrinkCardBatch;
use app\model\MemberPointLog;
use app\model\Order;
use app\model\OrderItem;
use app\support\Result;
use app\support\Sn;
use Illuminate\Database\Capsule\Manager as Db;

class OrderService
{
    private const array PAY_TYPES = [
        Order::PAY_TYPE_WECHAT,
        Order::PAY_TYPE_BALANCE,
        Order::PAY_TYPE_GIFT,
        Order::PAY_TYPE_DRINK_CARD,
    ];

    /**
     * 结算预览：金额与抵扣方案全部由服务端计算
     *
     * @param array<int, array{goods_id: int|string, quantity: int|string}> $items
     * @return array<string, mixed>
     */
    public static function preview(int $memberId, array $items): array
    {
        $member  = Member::query()->findOrFail($memberId);
        // 预览用非严格模式：商品被下架/删除时自动剔除而不是直接报错卡住，交由前端清理购物车
        $checked = self::resolveItems($items, false, false);

        $totalAmount = $checked['total_amount'];

        return [
            'items'                     => $checked['items'],
            'removed_items'             => $checked['removed_items'],
            'total_amount'              => $totalAmount,
            'pay_amount'                => $totalAmount,
            'balance'                   => (int)$member->balance,
            'gift_balance'              => (int)$member->gift_balance,
            'drink_card_balance'        => (int)$member->drink_card_balance,
            // 选择酒水卡/饮品卡支付时需要消耗的凭证数量（仅当对应 pay_options.*.available 为 true 时整单才可被该凭证覆盖）
            'gift_payable_amount'       => $checked['gift_payable_amount'],
            'drink_card_payable_amount' => $checked['drink_card_payable_amount'],
            'pay_options'               => self::buildPayOptions($member, $totalAmount, $checked),
        ];
    }

    /**
     * 计算「微信支付/余额支付/酒水卡支付/饮品卡支付」四种支付方式各自是否可用：
     * 每种非微信支付方式都是独立扣款，不再跟其他账户组合，因此某账户要么足额覆盖整单，要么不可选。
     *
     * @param array{gift_payable_amount: int, gift_cash_cap: int, drink_card_payable_amount: int, drink_card_cash_cap: int} $checked
     * @return array<string, array{available: bool, usable: bool}>
     */
    private static function buildPayOptions(Member $member, int $totalAmount, array $checked): array
    {
        $giftEnabled = SettingService::int('order', 'gift_pay_enabled', 1) === 1;

        $giftAvailable = $giftEnabled && $totalAmount > 0 && $checked['gift_cash_cap'] === $totalAmount;
        $giftUsable    = $giftAvailable && (int)$member->gift_balance >= $checked['gift_payable_amount'];

        $drinkCardAvailable = $totalAmount > 0 && $checked['drink_card_cash_cap'] === $totalAmount;
        $drinkCardUsable    = $drinkCardAvailable && (int)$member->drink_card_balance >= $checked['drink_card_payable_amount'];

        return [
            'balance'    => ['available' => true, 'usable' => $totalAmount > 0 && (int)$member->balance >= $totalAmount],
            'gift'       => ['available' => $giftAvailable, 'usable' => $giftUsable],
            'drink_card' => ['available' => $drinkCardAvailable, 'usable' => $drinkCardUsable],
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
        if (!in_array($payType, self::PAY_TYPES, true)) {
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
            $order->daily_no     = self::nextDailyNo();
            $order->member_id    = $memberId;
            $order->table_id     = (int)$table->id;
            $order->table_name   = trim(($table->zone_name !== '' ? $table->zone_name . ' ' : '') . (string)$table->name);
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
                $orderItem->drink_card_gift_amount      = $item['drink_card_gift_amount'];
                $orderItem->drink_card_gift_expire_days = $item['drink_card_gift_expire_days'];
                $orderItem->save();
            }

            self::reduceStock($checked['items']);

            if ($payType !== Order::PAY_TYPE_WECHAT) {
                self::payByAccount(
                    $member,
                    $order,
                    $payType,
                    $checked['gift_payable_amount'],
                    $checked['gift_cash_cap'],
                    $checked['drink_card_payable_amount'],
                    $checked['drink_card_cash_cap']
                );
            }

            return (int)$order->id;
        });

        // 支付事务提交后再推送打印，避免占用会员/库存行锁；打印失败不影响下单结果
        if ($payType !== Order::PAY_TYPE_WECHAT) {
            PrinterService::autoPrint($orderId);
        }

        return self::payResult($memberId, $orderId);
    }

    /**
     * 待支付订单重新发起支付
     *
     * @return array<string, mixed>
     */
    public static function pay(int $memberId, int $orderId, int $payType): array
    {
        if (!in_array($payType, self::PAY_TYPES, true)) {
            throw new BusinessException('支付方式不正确');
        }

        Db::connection()->transaction(static function () use ($memberId, $orderId, $payType): void {
            $member = AccountService::lockMember($memberId);
            $order  = self::lockOrder($memberId, $orderId);

            self::assertPayable($order);

            $order->pay_type = $payType;
            $order->save();

            if ($payType !== Order::PAY_TYPE_WECHAT) {
                [$giftUnits, $giftCashCap]           = self::giftPayableAmountOfOrder($order);
                [$drinkCardUnits, $drinkCardCashCap]  = self::drinkCardPayableAmountOfOrder($order);
                self::payByAccount($member, $order, $payType, $giftUnits, $giftCashCap, $drinkCardUnits, $drinkCardCashCap);
            }
        });

        if ($payType !== Order::PAY_TYPE_WECHAT) {
            PrinterService::autoPrint($orderId);
        }

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
        $orderId = Db::connection()->transaction(static function () use ($orderNo, $transactionId, $paidAmount): ?int {
            $order = Order::query()->where('order_no', $orderNo)->lockForUpdate()->first();
            if ($order === null) {
                throw new BusinessException('订单不存在', Result::NOT_FOUND);
            }
            if ((int)$order->pay_status === Order::PAY_STATUS_PAID) {
                return null;
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

            return (int)$order->id;
        });

        // 事务提交后再推送打印，且已支付过（幂等命中）时不重复打印
        if ($orderId !== null) {
            PrinterService::autoPrint($orderId);
        }
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
            'pay_balance'    => (int)$order->pay_balance,
            'pay_gift'       => (int)$order->pay_gift,
            'pay_drink_card' => (int)$order->pay_drink_card,
            'pay_wechat'     => (int)$order->pay_wechat,
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
     * 非微信支付的三种账户各自独立扣款：本单必须能被该账户全额覆盖才允许使用，不再跟其他账户组合
     */
    private static function payByAccount(
        Member $member,
        Order $order,
        int $payType,
        int $giftPayableAmount,
        int $giftCashCap,
        int $drinkCardPayableAmount,
        int $drinkCardCashCap
    ): void {
        $payAmount    = (int)$order->pay_amount;
        $payGift      = 0;
        $payDrinkCard = 0;
        $payBalance   = 0;

        switch ($payType) {
            case Order::PAY_TYPE_BALANCE:
                if ((int)$member->balance < $payAmount) {
                    throw new BusinessException('余额不足，请先充值');
                }
                $payBalance = $payAmount;
                break;

            case Order::PAY_TYPE_GIFT:
                $giftEnabled = SettingService::int('order', 'gift_pay_enabled', 1) === 1;
                if (!$giftEnabled || $giftCashCap !== $payAmount) {
                    throw new BusinessException(SettingService::giftDisplayName() . '暂不支持支付本单，请选择其他支付方式');
                }
                if ((int)$member->gift_balance < $giftPayableAmount) {
                    throw new BusinessException(SettingService::giftDisplayName() . '余额不足，请先充值');
                }
                $payGift = $giftPayableAmount;
                break;

            case Order::PAY_TYPE_DRINK_CARD:
                if ($drinkCardCashCap !== $payAmount) {
                    throw new BusinessException(SettingService::drinkCardDisplayName() . '暂不支持支付本单，请选择其他支付方式');
                }
                if ((int)$member->drink_card_balance < $drinkCardPayableAmount) {
                    throw new BusinessException(SettingService::drinkCardDisplayName() . '余额不足，请先充值');
                }
                $payDrinkCard = $drinkCardPayableAmount;
                break;

            default:
                throw new BusinessException('支付方式不正确');
        }

        if ($payGift > 0) {
            AccountService::decreaseGift($member, $payGift, MemberBalanceLog::BIZ_CONSUME, (int)$order->id, (string)$order->order_no, '点单消费');
        }
        if ($payDrinkCard > 0) {
            AccountService::decreaseDrinkCard($member, $payDrinkCard, MemberBalanceLog::BIZ_CONSUME, (int)$order->id, (string)$order->order_no, '点单消费');
        }
        if ($payBalance > 0) {
            AccountService::decreaseBalance($member, $payBalance, MemberBalanceLog::BIZ_CONSUME, (int)$order->id, (string)$order->order_no, '点单消费');
        }

        $order->pay_balance    = $payBalance;
        $order->pay_gift       = $payGift;
        $order->pay_drink_card = $payDrinkCard;
        $order->pay_status     = Order::PAY_STATUS_PAID;
        $order->order_status   = Order::STATUS_PAID;
        $order->paid_at        = date('Y-m-d H:i:s');
        $order->save();

        self::afterPaid($member, $order);
    }

    /**
     * 支付成功后的通用处理：累计消费、赠送礼品卡、增加销量
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

            $giftAmount = (int)$item->drink_card_gift_amount * (int)$item->quantity;
            if ($giftAmount > 0) {
                AccountService::grantDrinkCard(
                    $member,
                    $giftAmount,
                    MemberDrinkCardBatch::SOURCE_ORDER_GIFT,
                    (int)$order->id,
                    (int)$item->drink_card_gift_expire_days,
                    (string)$order->order_no,
                    "购买赠送-{$item->goods_name}"
                );
            }
        }
    }

    /**
     * 校验商品并计算金额，$lock 为 true 时对商品行加锁
     * $strict 为 false 时（仅预览场景使用），商品被下架/删除不再抛异常中断，
     * 而是剔除后计入 removed_items 返回给前端，由前端自动清理购物车，避免用户永久卡在结算页
     *
     * @param array<int, array{goods_id: int|string, quantity: int|string}> $items
     * @return array{items: array<int, array<string, mixed>>, removed_items: array<int, array{goods_id: int, goods_name: string}>, total_amount: int, gift_payable_amount: int, gift_cash_cap: int, drink_card_payable_amount: int, drink_card_cash_cap: int}
     */
    private static function resolveItems(array $items, bool $lock = false, bool $strict = true): array
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

        $giftUnit               = SettingService::giftUnit();
        $drinkCardUnit          = SettingService::drinkCardUnit();
        $resolved               = [];
        $removed                = [];
        $totalAmount            = 0;
        $giftPayableAmount      = 0;
        $giftCashCap            = 0;
        $drinkCardPayableAmount = 0;
        $drinkCardCashCap       = 0;

        foreach ($quantities as $goodsId => $quantity) {
            /** @var Goods|null $goods */
            $goods = $goodsList->get($goodsId);
            if ($goods === null || (int)$goods->status !== Goods::STATUS_ON) {
                if ($strict) {
                    throw new BusinessException('商品已下架，请重新选择');
                }
                $removed[] = ['goods_id' => $goodsId, 'goods_name' => (string)($goods?->name ?? '')];
                continue;
            }
            if ((int)$goods->stock !== Goods::STOCK_UNLIMITED && (int)$goods->stock < $quantity) {
                throw new BusinessException("「{$goods->name}」库存不足");
            }

            $subtotal    = (int)$goods->price * $quantity;
            $totalAmount += $subtotal;
            if ((int)$goods->gift_payable === 1) {
                // 赠金消耗量由商品自行配置(固定值)，与现金售价无关
                $giftPayableAmount += (int)$goods->gift_amount * $quantity;
                // “元”单位下赠金与现金同一记账单位(分)可直接抵扣；“张”单位下赠金是与现金无关的兑换凭证，抵满数量即视为该商品现金全额被兑换
                $giftCashCap += $giftUnit === '张' ? $subtotal : (int)$goods->gift_amount * $quantity;
            }
            if ((int)$goods->drink_card_payable === 1) {
                // 饮品卡消耗量同样由商品自行配置(固定值)，与赠金额度相互独立
                $drinkCardPayableAmount += (int)$goods->drink_card_amount * $quantity;
                $drinkCardCashCap += $drinkCardUnit === '张' ? $subtotal : (int)$goods->drink_card_amount * $quantity;
            }

            $resolved[] = [
                'goods_id'                    => (int)$goods->id,
                'goods_name'                  => (string)$goods->name,
                'goods_cover'                 => (string)$goods->cover,
                'price'                       => (int)$goods->price,
                'quantity'                    => $quantity,
                'subtotal'                    => $subtotal,
                'gift_payable'                => (int)$goods->gift_payable,
                'drink_card_payable'          => (int)$goods->drink_card_payable,
                'drink_card_gift_amount'      => (int)$goods->drink_card_gift_amount,
                'drink_card_gift_expire_days' => (int)$goods->drink_card_gift_expire_days,
            ];
        }

        if ($strict && $totalAmount <= 0) {
            throw new BusinessException('订单金额不正确');
        }

        return [
            'items'                     => $resolved,
            'removed_items'             => $removed,
            'total_amount'              => $totalAmount,
            'gift_payable_amount'       => $giftPayableAmount,
            'gift_cash_cap'             => $giftCashCap,
            'drink_card_payable_amount' => $drinkCardPayableAmount,
            'drink_card_cash_cap'       => $drinkCardCashCap,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    /**
     * 生成当日出单序号，每天从1开始递增，用于叫号/小票打印
     */
    private static function nextDailyNo(): int
    {
        $today = date('Y-m-d');

        // 原子 upsert：借助 LAST_INSERT_ID(expr) 让本次自增结果可被读取，避免额外加锁
        // 注意：该表以 biz_date 作主键、不含 AUTO_INCREMENT 列，否则 MySQL 会用自增列的值覆盖 LAST_INSERT_ID(expr) 的显式赋值
        Db::connection()->statement(
            'INSERT INTO nf_order_daily_sequence (biz_date, seq, created_at, updated_at) VALUES (?, LAST_INSERT_ID(1), NOW(), NOW())
             ON DUPLICATE KEY UPDATE seq = LAST_INSERT_ID(seq + 1), updated_at = NOW()',
            [$today]
        );

        return (int)Db::connection()->getPdo()->lastInsertId();
    }

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

    /**
     * @return array{0: int, 1: int} [需消耗的赠金凭证数量, 对应可抵扣的现金上限(分)]
     */
    private static function giftPayableAmountOfOrder(Order $order): array
    {
        $giftUnit = SettingService::giftUnit();
        $amount   = 0;
        $cashCap  = 0;
        foreach ($order->items as $item) {
            $goods = Goods::query()->find((int)$item->goods_id);
            if ($goods !== null && (int)$goods->gift_payable === 1) {
                $amount   += (int)$goods->gift_amount * (int)$item->quantity;
                $cashCap  += $giftUnit === '张' ? (int)$item->subtotal : (int)$goods->gift_amount * (int)$item->quantity;
            }
        }

        return [$amount, $cashCap];
    }

    /**
     * @return array{0: int, 1: int} [需消耗的饮品卡凭证数量, 对应可抵扣的现金上限(分)]
     */
    private static function drinkCardPayableAmountOfOrder(Order $order): array
    {
        $drinkCardUnit = SettingService::drinkCardUnit();
        $amount        = 0;
        $cashCap       = 0;
        foreach ($order->items as $item) {
            $goods = Goods::query()->find((int)$item->goods_id);
            if ($goods !== null && (int)$goods->drink_card_payable === 1) {
                $amount  += (int)$goods->drink_card_amount * (int)$item->quantity;
                $cashCap += $drinkCardUnit === '张' ? (int)$item->subtotal : (int)$goods->drink_card_amount * (int)$item->quantity;
            }
        }

        return [$amount, $cashCap];
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
            'daily_no'          => (int)$order->daily_no,
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
