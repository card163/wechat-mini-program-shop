<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\Member;
use app\model\MemberBalanceLog;
use app\model\MemberGiftBatch;
use app\model\MemberPointLog;
use app\model\RechargeOrder;
use app\model\RechargePackage;
use app\support\Result;
use app\support\Sn;
use Illuminate\Database\Capsule\Manager as Db;

class RechargeService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function packages(): array
    {
        return RechargePackage::query()
            ->where('status', RechargePackage::STATUS_ON)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(static fn(RechargePackage $package): array => [
                'id'               => (int)$package->id,
                'title'            => (string)$package->title,
                'amount'           => (int)$package->amount,
                'gift_amount'      => (int)$package->gift_amount,
                'gift_point'       => (int)$package->gift_point,
                'gift_expire_days' => (int)$package->gift_expire_days,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function create(int $memberId, int $packageId): array
    {
        $package = RechargePackage::query()->where('status', RechargePackage::STATUS_ON)->find($packageId);
        if ($package === null) {
            throw new BusinessException('充值套餐不存在');
        }

        $member = Member::query()->findOrFail($memberId);

        $order = new RechargeOrder();
        $order->order_no    = Sn::make(Sn::RECHARGE);
        $order->member_id   = $memberId;
        $order->package_id  = (int)$package->id;
        $order->amount      = (int)$package->amount;
        $order->gift_amount = (int)$package->gift_amount;
        $order->gift_point  = (int)$package->gift_point;
        $order->pay_status  = RechargeOrder::PAY_STATUS_UNPAID;
        $order->save();

        $payParams = WechatPayService::jsapiPay(
            (string)$order->order_no,
            (int)$order->amount,
            '会员充值-' . (string)$package->title,
            (string)$member->openid
        );

        return [
            'recharge_id' => (int)$order->id,
            'order_no'    => (string)$order->order_no,
            'amount'      => (int)$order->amount,
            'pay_params'  => $payParams,
        ];
    }

    /**
     * 支付成功入账，必须幂等
     */
    public static function markPaid(string $orderNo, string $transactionId, int $paidAmount): void
    {
        Db::connection()->transaction(static function () use ($orderNo, $transactionId, $paidAmount): void {
            $order = RechargeOrder::query()->where('order_no', $orderNo)->lockForUpdate()->first();
            if ($order === null) {
                throw new BusinessException('充值订单不存在', Result::NOT_FOUND);
            }
            if ((int)$order->pay_status === RechargeOrder::PAY_STATUS_PAID) {
                return;
            }
            if ($paidAmount !== (int)$order->amount) {
                throw new BusinessException('支付金额与充值订单不一致');
            }

            $order->transaction_id = $transactionId;
            $order->pay_status     = RechargeOrder::PAY_STATUS_PAID;
            $order->paid_at        = date('Y-m-d H:i:s');
            $order->save();

            $member = AccountService::lockMember((int)$order->member_id);

            AccountService::increaseBalance(
                $member,
                (int)$order->amount,
                MemberBalanceLog::BIZ_RECHARGE,
                (int)$order->id,
                (string)$order->order_no,
                '充值到账'
            );

            if ((int)$order->gift_amount > 0) {
                $package    = RechargePackage::query()->find((int)$order->package_id);
                $expireDays = $package === null ? 0 : (int)$package->gift_expire_days;

                AccountService::grantGift(
                    $member,
                    (int)$order->gift_amount,
                    MemberGiftBatch::SOURCE_RECHARGE,
                    (int)$order->id,
                    $expireDays,
                    (string)$order->order_no,
                    '充值赠送'
                );
            }

            if ((int)$order->gift_point > 0) {
                AccountService::changePoint(
                    $member,
                    (int)$order->gift_point,
                    MemberPointLog::BIZ_STORE_IN,
                    (int)$order->id,
                    '充值赠送记分牌'
                );
            }

            $member->total_recharge = (int)$member->total_recharge + (int)$order->amount;
            $member->save();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(int $memberId, int $rechargeId): array
    {
        $order = RechargeOrder::query()->where('member_id', $memberId)->find($rechargeId);
        if ($order === null) {
            throw new BusinessException('充值订单不存在', Result::NOT_FOUND);
        }

        return self::format($order);
    }

    /**
     * @return array{list: array<int, array<string, mixed>>, total: int}
     */
    public static function paginate(int $memberId, int $page, int $pageSize): array
    {
        $query = RechargeOrder::query()->where('member_id', $memberId);
        $total = (int)$query->count();
        $list  = $query->orderByDesc('id')
            ->forPage($page, $pageSize)
            ->get()
            ->map(static fn(RechargeOrder $order): array => self::format($order))
            ->all();

        return ['list' => $list, 'total' => $total];
    }

    /**
     * @return array<string, mixed>
     */
    private static function format(RechargeOrder $order): array
    {
        return [
            'id'          => (int)$order->id,
            'order_no'    => (string)$order->order_no,
            'amount'      => (int)$order->amount,
            'gift_amount' => (int)$order->gift_amount,
            'gift_point'  => (int)$order->gift_point,
            'pay_status'  => (int)$order->pay_status,
            'paid_at'     => $order->paid_at === null ? null : (string)$order->paid_at,
            'created_at'  => (string)$order->created_at,
        ];
    }
}
