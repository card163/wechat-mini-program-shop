<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\model\Member;
use app\model\Order;
use app\model\RechargeOrder;
use app\support\Result;
use Illuminate\Database\Capsule\Manager as Db;
use support\Request;
use support\Response;

class StatController
{
    public function overview(): Response
    {
        $today = date('Y-m-d');
        $start = $today . ' 00:00:00';
        $end   = $today . ' 23:59:59';

        $paidOrders = Order::query()
            ->where('pay_status', Order::PAY_STATUS_PAID)
            ->whereBetween('paid_at', [$start, $end]);

        return Result::success([
            'today_amount'   => (int)(clone $paidOrders)->sum('pay_amount'),
            'today_orders'   => (int)(clone $paidOrders)->count(),
            'today_members'  => (int)Member::query()->whereBetween('created_at', [$start, $end])->count(),
            'today_recharge' => (int)RechargeOrder::query()
                ->where('pay_status', RechargeOrder::PAY_STATUS_PAID)
                ->whereBetween('paid_at', [$start, $end])
                ->sum('amount'),
            'total_members'  => (int)Member::query()->count(),
            'pending_orders' => (int)Order::query()->where('order_status', Order::STATUS_PAID)->count(),
        ]);
    }

    public function trend(Request $request): Response
    {
        $days  = min(90, max(1, (int)$request->get('days', 7)));
        $start = date('Y-m-d', strtotime('-' . ($days - 1) . ' days')) . ' 00:00:00';

        $rows = Order::query()
            ->selectRaw('DATE(paid_at) AS date, SUM(pay_amount) AS amount, COUNT(*) AS orders')
            ->where('pay_status', Order::PAY_STATUS_PAID)
            ->where('paid_at', '>=', $start)
            ->groupBy(Db::connection()->raw('DATE(paid_at)'))
            ->get()
            ->keyBy('date');

        $list = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $row  = $rows->get($date);
            $list[] = [
                'date'   => $date,
                'amount' => $row === null ? 0 : (int)$row->amount,
                'orders' => $row === null ? 0 : (int)$row->orders,
            ];
        }

        return Result::success($list);
    }
}
