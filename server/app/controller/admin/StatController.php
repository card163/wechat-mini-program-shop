<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\model\Member;
use app\model\Order;
use app\model\RechargeOrder;
use app\service\SettingService;
use app\support\Result;
use support\Request;
use support\Response;

class StatController
{
    public function overview(): Response
    {
        $today = date('Y-m-d');
        $start = $today . ' 00:00:00';
        $end   = $today . ' 23:59:59';

        $yesterday      = date('Y-m-d', strtotime('-1 day'));
        $yesterdayStart = $yesterday . ' 00:00:00';
        $yesterdayEnd   = $yesterday . ' 23:59:59';

        $paidOrders = Order::query()
            ->where('pay_status', Order::PAY_STATUS_PAID)
            ->whereBetween('paid_at', [$start, $end]);

        $channels = self::sumPayChannels((clone $paidOrders)->get([
            'pay_type', 'pay_amount', 'pay_wechat', 'pay_balance', 'pay_gift', 'pay_drink_card',
        ]));

        $yesterdayChannels = self::sumPayChannels(Order::query()
            ->where('pay_status', Order::PAY_STATUS_PAID)
            ->whereBetween('paid_at', [$yesterdayStart, $yesterdayEnd])
            ->get(['pay_type', 'pay_amount', 'pay_wechat', 'pay_balance', 'pay_gift', 'pay_drink_card']));

        return Result::success([
            'today_amount'      => (int)(clone $paidOrders)->sum('pay_amount'),
            'today_orders'      => (int)(clone $paidOrders)->count(),
            'today_members'     => (int)Member::query()->whereBetween('created_at', [$start, $end])->count(),
            'today_recharge'    => (int)RechargeOrder::query()
                ->where('pay_status', RechargeOrder::PAY_STATUS_PAID)
                ->whereBetween('paid_at', [$start, $end])
                ->sum('amount'),
            'today_pay_wechat'           => $channels['wechat'],
            'today_pay_balance'          => $channels['balance'],
            'today_pay_gift_units'       => $channels['gift_units'],
            'today_pay_drink_card_units' => $channels['drink_card_units'],
            'yesterday_pay_wechat'           => $yesterdayChannels['wechat'],
            'yesterday_pay_balance'          => $yesterdayChannels['balance'],
            'yesterday_pay_gift_units'       => $yesterdayChannels['gift_units'],
            'yesterday_pay_drink_card_units' => $yesterdayChannels['drink_card_units'],
            'total_members'  => (int)Member::query()->count(),
            'pending_orders' => (int)Order::query()->where('order_status', Order::STATUS_PAID)->count(),
        ]);
    }

    public function trend(Request $request): Response
    {
        $days  = min(90, max(1, (int)$request->get('days', 7)));
        $start = date('Y-m-d', strtotime('-' . ($days - 1) . ' days')) . ' 00:00:00';

        $rows = Order::query()
            ->where('pay_status', Order::PAY_STATUS_PAID)
            ->where('paid_at', '>=', $start)
            ->get(['paid_at', 'pay_type', 'pay_amount', 'pay_wechat', 'pay_balance', 'pay_gift', 'pay_drink_card']);

        $byDate = [];
        foreach ($rows as $row) {
            $date = date('Y-m-d', strtotime((string)$row->paid_at));
            $byDate[$date] ??= [];
            $byDate[$date][] = $row;
        }

        $list = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date     = date('Y-m-d', strtotime("-$i days"));
            $dayRows  = $byDate[$date] ?? [];
            $channels = self::sumPayChannels($dayRows);

            $list[] = [
                'date'              => $date,
                'amount'            => array_sum(array_map(static fn($row): int => (int)$row->pay_amount, $dayRows)),
                'orders'            => count($dayRows),
                'wechat_amount'     => $channels['wechat'],
                'balance_amount'    => $channels['balance'],
                'gift_amount'       => $channels['gift'],
                'drink_card_amount' => $channels['drink_card'],
                'gift_units'        => $channels['gift_units'],
                'drink_card_units'  => $channels['drink_card_units'],
            ];
        }

        return Result::success($list);
    }

    /**
     * 按支付渠道汇总：微信/余额字段本身就是现金（分），直接求和；
     * 酒水卡/饮品卡改为汇总实际消耗的凭证张数（`pay_gift`/`pay_drink_card` 原始字段，
     * 纯卡支付、微信组合支付两种场景下都已是真实消耗张数），不再折算成金额，避免"兑法币汇率"带来的近似误差。
     *
     * @param iterable<Order> $rows
     * @return array{wechat: int, balance: int, gift: int, drink_card: int, gift_units: int, drink_card_units: int}
     */
    private static function sumPayChannels(iterable $rows): array
    {
        $giftRate      = SettingService::giftCashRate();
        $drinkCardRate = SettingService::drinkCardCashRate();

        $wechat = $balance = $gift = $drinkCard = 0;
        $giftUnits = $drinkCardUnits = 0;
        foreach ($rows as $row) {
            $wechat  += (int)$row->pay_wechat;
            $balance += (int)$row->pay_balance;
            $giftUnits      += (int)$row->pay_gift;
            $drinkCardUnits += (int)$row->pay_drink_card;

            switch ((int)$row->pay_type) {
                case Order::PAY_TYPE_GIFT:
                    $gift += (int)$row->pay_amount;
                    break;
                case Order::PAY_TYPE_DRINK_CARD:
                    $drinkCard += (int)$row->pay_amount;
                    break;
                case Order::PAY_TYPE_WECHAT_COMBO:
                    $gift      += (int)$row->pay_gift * $giftRate;
                    $drinkCard += (int)$row->pay_drink_card * $drinkCardRate;
                    break;
            }
        }

        return [
            'wechat' => $wechat, 'balance' => $balance, 'gift' => $gift, 'drink_card' => $drinkCard,
            'gift_units' => $giftUnits, 'drink_card_units' => $drinkCardUnits,
        ];
    }
}
