<?php

declare(strict_types=1);

namespace app\service;

use app\model\Member;
use app\model\MemberBalanceLog;
use app\model\MemberGiftBatch;
use app\model\MemberPointLog;
use app\model\Order;
use app\model\WineStorage;

class MemberService
{
    /**
     * @return array<string, mixed>
     */
    public static function info(int $memberId): array
    {
        $member = Member::query()->findOrFail($memberId);

        return MemberAuthService::profile($member) + [
            'total_recharge' => (int)$member->total_recharge,
            'total_consume'  => (int)$member->total_consume,
            'order_count'    => [
                'unpaid' => (int)Order::query()->where('member_id', $memberId)->where('order_status', Order::STATUS_UNPAID)->count(),
                'paid'   => (int)Order::query()->where('member_id', $memberId)->where('order_status', Order::STATUS_PAID)->count(),
            ],
            'wine_count'     => (int)WineStorage::query()->where('member_id', $memberId)->where('status', WineStorage::STATUS_STORING)->count(),
        ];
    }

    /**
     * @return array{list: array<int, array<string, mixed>>, total: int}
     */
    public static function balanceLogs(int $memberId, int $accountType, int $page, int $pageSize): array
    {
        $query = MemberBalanceLog::query()
            ->where('member_id', $memberId)
            ->where('account_type', $accountType);

        $total = (int)$query->count();
        $list  = $query->orderByDesc('id')
            ->forPage($page, $pageSize)
            ->get()
            ->map(static fn(MemberBalanceLog $log): array => [
                'id'            => (int)$log->id,
                'amount'        => (int)$log->amount,
                'after_balance' => (int)$log->after_balance,
                'biz_type'      => (int)$log->biz_type,
                'biz_type_text' => self::balanceBizText((int)$log->biz_type),
                'biz_no'        => (string)$log->biz_no,
                'gift_batch_id' => (int)$log->gift_batch_id,
                'remark'        => (string)$log->remark,
                'created_at'    => (string)$log->created_at,
            ])
            ->all();

        return ['list' => $list, 'total' => $total];
    }

    /**
     * @return array{list: array<int, array<string, mixed>>, total: int}
     */
    public static function pointLogs(int $memberId, int $page, int $pageSize): array
    {
        $query = MemberPointLog::query()->where('member_id', $memberId);

        $total = (int)$query->count();
        $list  = $query->orderByDesc('id')
            ->forPage($page, $pageSize)
            ->get()
            ->map(static fn(MemberPointLog $log): array => [
                'id'            => (int)$log->id,
                'point'         => (int)$log->point,
                'after_point'   => (int)$log->after_point,
                'biz_type'      => (int)$log->biz_type,
                'biz_type_text' => self::pointBizText((int)$log->biz_type),
                'remark'        => (string)$log->remark,
                'created_at'    => (string)$log->created_at,
            ])
            ->all();

        return ['list' => $list, 'total' => $total];
    }

    /**
     * @return array{summary: array<string, mixed>, list: array<int, array<string, mixed>>, total: int}
     */
    public static function giftBatches(int $memberId, ?int $status, int $page, int $pageSize): array
    {
        $member = Member::query()->findOrFail($memberId);

        $query = MemberGiftBatch::query()->where('member_id', $memberId);
        if ($status !== null) {
            $query->where('status', $status);
        }

        $total = (int)$query->count();
        $list  = $query->orderByDesc('id')
            ->forPage($page, $pageSize)
            ->get()
            ->map(static fn(MemberGiftBatch $batch): array => [
                'id'               => (int)$batch->id,
                'amount'           => (int)$batch->amount,
                'used_amount'      => (int)$batch->used_amount,
                'remain_amount'    => (int)$batch->remain_amount,
                'source_type'      => (int)$batch->source_type,
                'source_type_text' => self::giftSourceText((int)$batch->source_type),
                'status'           => (int)$batch->status,
                'status_text'      => self::giftStatusText((int)$batch->status),
                'expired_at'       => $batch->expired_at === null ? null : (string)$batch->expired_at,
                'created_at'       => (string)$batch->created_at,
            ])
            ->all();

        $expiring = MemberGiftBatch::query()
            ->where('member_id', $memberId)
            ->where('status', MemberGiftBatch::STATUS_VALID)
            ->where('remain_amount', '>', 0)
            ->whereNotNull('expired_at')
            ->orderBy('expired_at')
            ->first();

        return [
            'summary' => [
                'gift_balance'     => (int)$member->gift_balance,
                'expiring_amount'  => $expiring === null ? 0 : (int)$expiring->remain_amount,
                'expiring_at'      => $expiring === null ? null : (string)$expiring->expired_at,
            ],
            'list'  => $list,
            'total' => $total,
        ];
    }

    public static function balanceBizText(int $bizType): string
    {
        return match ($bizType) {
            MemberBalanceLog::BIZ_RECHARGE       => '充值',
            MemberBalanceLog::BIZ_RECHARGE_GIFT  => '充值赠送',
            MemberBalanceLog::BIZ_CONSUME        => '消费',
            MemberBalanceLog::BIZ_REFUND         => '退款',
            MemberBalanceLog::BIZ_POINT_EXCHANGE => '记分牌兑换',
            MemberBalanceLog::BIZ_GIFT_EXPIRED   => '赠金过期',
            MemberBalanceLog::BIZ_ADMIN_ADJUST   => '管理员调整',
            default                              => '其他',
        };
    }

    public static function pointBizText(int $bizType): string
    {
        return match ($bizType) {
            MemberPointLog::BIZ_STORE_IN        => '店内存记分牌',
            MemberPointLog::BIZ_CONSUME_GAIN    => '消费获得',
            MemberPointLog::BIZ_EXCHANGE_GIFT   => '兑换赠金',
            MemberPointLog::BIZ_EXCHANGE_GOODS  => '兑换商品',
            MemberPointLog::BIZ_ADMIN_ADJUST    => '管理员调整',
            MemberPointLog::BIZ_REFUND_ROLLBACK => '退款回滚',
            default                             => '其他',
        };
    }

    public static function giftSourceText(int $sourceType): string
    {
        return match ($sourceType) {
            MemberGiftBatch::SOURCE_RECHARGE => '充值赠送',
            MemberGiftBatch::SOURCE_EXCHANGE => '记分牌兑换',
            MemberGiftBatch::SOURCE_ADMIN    => '管理员发放',
            MemberGiftBatch::SOURCE_REFUND   => '订单退回',
            default                          => '其他',
        };
    }

    public static function giftStatusText(int $status): string
    {
        return match ($status) {
            MemberGiftBatch::STATUS_VALID   => '有效',
            MemberGiftBatch::STATUS_USED_UP => '已用完',
            MemberGiftBatch::STATUS_EXPIRED => '已过期',
            default                         => '未知',
        };
    }
}
