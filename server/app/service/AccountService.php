<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\Member;
use app\model\MemberBalanceLog;
use app\model\MemberGiftBatch;
use app\model\MemberPointLog;
use Illuminate\Database\Capsule\Manager as Db;

/**
 * 资金与记分牌账户引擎
 *
 * 约定：
 * - 所有方法都必须在外层事务中调用，内部对会员行加 FOR UPDATE 行锁
 * - 金额单位为分，只接受正整数入参，增减由方法语义决定
 * - 任何余额/赠金/记分牌变动都会写入对应流水表
 */
class AccountService
{
    /**
     * 加锁读取会员，防止并发下余额被覆盖
     */
    public static function lockMember(int $memberId): Member
    {
        $member = Member::query()->lockForUpdate()->find($memberId);
        if ($member === null) {
            throw new BusinessException('会员不存在');
        }
        return $member;
    }

    /**
     * 本金入账
     */
    public static function increaseBalance(
        Member $member,
        int $amount,
        int $bizType,
        int $bizId = 0,
        string $bizNo = '',
        string $remark = '',
        int $operatorId = 0
    ): void {
        self::assertPositive($amount);

        $before = (int)$member->balance;
        $member->balance = $before + $amount;
        $member->save();

        self::writeBalanceLog($member, MemberBalanceLog::ACCOUNT_PRINCIPAL, $amount, $before, (int)$member->balance, $bizType, $bizId, $bizNo, $remark, $operatorId);
    }

    /**
     * 本金扣款
     */
    public static function decreaseBalance(
        Member $member,
        int $amount,
        int $bizType,
        int $bizId = 0,
        string $bizNo = '',
        string $remark = '',
        int $operatorId = 0
    ): void {
        self::assertPositive($amount);

        $before = (int)$member->balance;
        if ($before < $amount) {
            throw new BusinessException('余额不足');
        }

        $member->balance = $before - $amount;
        $member->save();

        self::writeBalanceLog($member, MemberBalanceLog::ACCOUNT_PRINCIPAL, -$amount, $before, (int)$member->balance, $bizType, $bizId, $bizNo, $remark, $operatorId);
    }

    /**
     * 发放赠金批次
     *
     * @param int $expireDays 有效天数，0 表示永久有效
     */
    public static function grantGift(
        Member $member,
        int $amount,
        int $sourceType,
        int $sourceId = 0,
        int $expireDays = 0,
        string $bizNo = '',
        string $remark = '',
        int $operatorId = 0
    ): MemberGiftBatch {
        self::assertPositive($amount);

        $batch = new MemberGiftBatch();
        $batch->member_id     = (int)$member->id;
        $batch->amount        = $amount;
        $batch->used_amount   = 0;
        $batch->remain_amount = $amount;
        $batch->source_type   = $sourceType;
        $batch->source_id     = $sourceId;
        $batch->effective_at  = date('Y-m-d H:i:s');
        $batch->expired_at    = $expireDays > 0 ? date('Y-m-d H:i:s', strtotime("+$expireDays days")) : null;
        $batch->status        = MemberGiftBatch::STATUS_VALID;
        $batch->remark        = $remark;
        $batch->save();

        $before = (int)$member->gift_balance;
        $member->gift_balance = $before + $amount;
        $member->save();

        self::writeBalanceLog(
            $member,
            MemberBalanceLog::ACCOUNT_GIFT,
            $amount,
            $before,
            (int)$member->gift_balance,
            $sourceType === MemberGiftBatch::SOURCE_EXCHANGE ? MemberBalanceLog::BIZ_POINT_EXCHANGE : MemberBalanceLog::BIZ_RECHARGE_GIFT,
            $sourceId,
            $bizNo,
            $remark,
            $operatorId,
            (int)$batch->id
        );

        return $batch;
    }

    /**
     * 赠金扣款：按到期时间由近及远消耗批次，永久有效批次最后消耗
     *
     * @return array<int, array{batch_id: int, amount: int}> 各批次实际扣减明细
     */
    public static function decreaseGift(
        Member $member,
        int $amount,
        int $bizType,
        int $bizId = 0,
        string $bizNo = '',
        string $remark = '',
        int $operatorId = 0
    ): array {
        self::assertPositive($amount);

        $now = date('Y-m-d H:i:s');
        /** @var MemberGiftBatch[] $batches */
        $batches = MemberGiftBatch::query()
            ->where('member_id', (int)$member->id)
            ->where('status', MemberGiftBatch::STATUS_VALID)
            ->where('remain_amount', '>', 0)
            ->where(static function ($query) use ($now): void {
                $query->whereNull('expired_at')->orWhere('expired_at', '>', $now);
            })
            ->orderByRaw('expired_at IS NULL ASC, expired_at ASC, id ASC')
            ->lockForUpdate()
            ->get()
            ->all();

        $available = array_sum(array_map(static fn(MemberGiftBatch $b): int => (int)$b->remain_amount, $batches));
        if ($available < $amount) {
            throw new BusinessException('赠金余额不足');
        }

        $details   = [];
        $remaining = $amount;
        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $deduct = min($remaining, (int)$batch->remain_amount);

            $batch->used_amount   = (int)$batch->used_amount + $deduct;
            $batch->remain_amount = (int)$batch->remain_amount - $deduct;
            if ($batch->remain_amount === 0) {
                $batch->status = MemberGiftBatch::STATUS_USED_UP;
            }
            $batch->save();

            $before = (int)$member->gift_balance;
            $member->gift_balance = $before - $deduct;
            $member->save();

            self::writeBalanceLog($member, MemberBalanceLog::ACCOUNT_GIFT, -$deduct, $before, (int)$member->gift_balance, $bizType, $bizId, $bizNo, $remark, $operatorId, (int)$batch->id);

            $details[] = ['batch_id' => (int)$batch->id, 'amount' => $deduct];
            $remaining -= $deduct;
        }

        return $details;
    }

    /**
     * 记分牌变动，$point 正数为增加、负数为扣减
     */
    public static function changePoint(
        Member $member,
        int $point,
        int $bizType,
        int $bizId = 0,
        string $remark = '',
        int $operatorId = 0
    ): void {
        if ($point === 0) {
            return;
        }

        $before = (int)$member->point;
        $after  = $before + $point;
        if ($after < 0) {
            throw new BusinessException('记分牌不足');
        }

        $member->point = $after;
        if ($point > 0) {
            $member->total_point = (int)$member->total_point + $point;
        }
        $member->save();

        $log = new MemberPointLog();
        $log->member_id    = (int)$member->id;
        $log->point        = $point;
        $log->before_point = $before;
        $log->after_point  = $after;
        $log->biz_type     = $bizType;
        $log->biz_id       = $bizId;
        $log->remark       = $remark;
        $log->operator_id  = $operatorId;
        $log->save();
    }

    /**
     * 处理到期赠金：批次置为已过期，并从会员赠金余额中扣除
     *
     * @return int 本次过期的赠金总额(分)
     */
    public static function expireGiftBatches(): int
    {
        $now    = date('Y-m-d H:i:s');
        $total  = 0;
        $batchIds = MemberGiftBatch::query()
            ->where('status', MemberGiftBatch::STATUS_VALID)
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', $now)
            ->pluck('id')
            ->all();

        foreach ($batchIds as $batchId) {
            $total += Db::connection()->transaction(static function () use ($batchId): int {
                $batch = MemberGiftBatch::query()->lockForUpdate()->find($batchId);
                if ($batch === null || (int)$batch->status !== MemberGiftBatch::STATUS_VALID) {
                    return 0;
                }

                $remain        = (int)$batch->remain_amount;
                $batch->status = MemberGiftBatch::STATUS_EXPIRED;
                $batch->save();

                if ($remain <= 0) {
                    return 0;
                }

                $member = self::lockMember((int)$batch->member_id);
                $before = (int)$member->gift_balance;
                $member->gift_balance = max(0, $before - $remain);
                $member->save();

                self::writeBalanceLog(
                    $member,
                    MemberBalanceLog::ACCOUNT_GIFT,
                    -$remain,
                    $before,
                    (int)$member->gift_balance,
                    MemberBalanceLog::BIZ_GIFT_EXPIRED,
                    (int)$batch->id,
                    '',
                    '赠金到期失效',
                    0,
                    (int)$batch->id
                );

                return $remain;
            });
        }

        return $total;
    }

    private static function writeBalanceLog(
        Member $member,
        int $accountType,
        int $amount,
        int $before,
        int $after,
        int $bizType,
        int $bizId,
        string $bizNo,
        string $remark,
        int $operatorId,
        int $giftBatchId = 0
    ): void {
        $log = new MemberBalanceLog();
        $log->member_id      = (int)$member->id;
        $log->account_type   = $accountType;
        $log->gift_batch_id  = $giftBatchId;
        $log->amount         = $amount;
        $log->before_balance = $before;
        $log->after_balance  = $after;
        $log->biz_type       = $bizType;
        $log->biz_id         = $bizId;
        $log->biz_no         = $bizNo;
        $log->remark         = $remark;
        $log->operator_id    = $operatorId;
        $log->save();
    }

    private static function assertPositive(int $amount): void
    {
        if ($amount <= 0) {
            throw new BusinessException('金额必须大于0');
        }
    }
}
