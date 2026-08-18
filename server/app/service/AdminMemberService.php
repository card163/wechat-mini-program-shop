<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\Member;
use app\model\MemberBalanceLog;
use app\model\MemberGiftBatch;
use app\model\MemberPointLog;
use Illuminate\Database\Capsule\Manager as Db;

class AdminMemberService
{
    /**
     * @return array{list: array<int, array<string, mixed>>, total: int}
     */
    public static function paginate(string $keyword, ?int $status, string $orderBy, int $page, int $pageSize): array
    {
        $query = Member::query();

        if ($keyword !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $keyword);
            $query->where(static function ($builder) use ($escaped): void {
                $builder->where('nickname', 'like', "%$escaped%")->orWhere('phone', 'like', "%$escaped%");
            });
        }
        if ($status !== null) {
            $query->where('status', $status);
        }

        $sortField = in_array($orderBy, ['balance', 'gift_balance', 'point', 'total_point', 'total_recharge', 'total_consume'], true)
            ? $orderBy
            : 'id';

        $total = (int)$query->count();
        $list  = $query->orderByDesc($sortField)
            ->forPage($page, $pageSize)
            ->get()
            ->map(static fn(Member $member): array => self::format($member))
            ->all();

        return ['list' => $list, 'total' => $total];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(int $memberId): array
    {
        $member = Member::query()->findOrFail($memberId);

        $batches = MemberGiftBatch::query()
            ->where('member_id', $memberId)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(static fn(MemberGiftBatch $batch): array => [
                'id'            => (int)$batch->id,
                'amount'        => (int)$batch->amount,
                'remain_amount' => (int)$batch->remain_amount,
                'status'        => (int)$batch->status,
                'status_text'   => MemberService::giftStatusText((int)$batch->status),
                'expired_at'    => $batch->expired_at === null ? null : (string)$batch->expired_at,
            ])
            ->all();

        return self::format($member) + ['gift_batches' => $batches];
    }

    public static function changeStatus(int $memberId, int $status): void
    {
        $member = Member::query()->findOrFail($memberId);
        $member->status = $status === Member::STATUS_NORMAL ? Member::STATUS_NORMAL : Member::STATUS_DISABLED;
        $member->save();
    }

    public static function updatePhone(int $memberId, string $phone): void
    {
        $phone = trim($phone);
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            throw new BusinessException('手机号格式不正确');
        }

        $exists = Member::query()
            ->where('phone', $phone)
            ->where('id', '!=', $memberId)
            ->exists();
        if ($exists) {
            throw new BusinessException('该手机号已被其他会员使用');
        }

        $member = Member::query()->findOrFail($memberId);
        $member->phone = $phone;
        $member->save();
    }

    /**
     * 调整本金余额，$amount 正数增加、负数扣减
     */
    public static function adjustBalance(int $memberId, int $amount, string $remark, int $operatorId): void
    {
        if ($amount === 0) {
            throw new BusinessException('调整金额不能为0');
        }
        if (trim($remark) === '') {
            throw new BusinessException('请填写调整原因');
        }

        Db::connection()->transaction(static function () use ($memberId, $amount, $remark, $operatorId): void {
            $member = AccountService::lockMember($memberId);

            $amount > 0
                ? AccountService::increaseBalance($member, $amount, MemberBalanceLog::BIZ_ADMIN_ADJUST, 0, '', $remark, $operatorId)
                : AccountService::decreaseBalance($member, -$amount, MemberBalanceLog::BIZ_ADMIN_ADJUST, 0, '', $remark, $operatorId);
        });
    }

    public static function grantGift(int $memberId, int $amount, int $expireDays, string $remark, int $operatorId): void
    {
        if ($amount <= 0) {
            throw new BusinessException('发放金额必须大于0');
        }

        Db::connection()->transaction(static function () use ($memberId, $amount, $expireDays, $remark, $operatorId): void {
            $member = AccountService::lockMember($memberId);
            AccountService::grantGift($member, $amount, MemberGiftBatch::SOURCE_ADMIN, 0, $expireDays, '', $remark, $operatorId);
        });
    }

    public static function adjustPoint(int $memberId, int $point, string $remark, int $operatorId): void
    {
        if ($point === 0) {
            throw new BusinessException('调整数量不能为0');
        }
        if (trim($remark) === '') {
            throw new BusinessException('请填写调整原因');
        }

        Db::connection()->transaction(static function () use ($memberId, $point, $remark, $operatorId): void {
            $member = AccountService::lockMember($memberId);
            AccountService::changePoint(
                $member,
                $point,
                $point > 0 ? MemberPointLog::BIZ_STORE_IN : MemberPointLog::BIZ_ADMIN_ADJUST,
                0,
                $remark,
                $operatorId
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function format(Member $member): array
    {
        return [
            'id'             => (int)$member->id,
            'nickname'       => MemberAuthService::displayName((string)$member->nickname),
            'avatar'         => (string)$member->avatar,
            'phone'          => (string)$member->phone,
            'balance'        => (int)$member->balance,
            'gift_balance'   => (int)$member->gift_balance,
            'point'          => (int)$member->point,
            'total_point'    => (int)$member->total_point,
            'total_recharge' => (int)$member->total_recharge,
            'total_consume'  => (int)$member->total_consume,
            'status'         => (int)$member->status,
            'remark'         => (string)$member->remark,
            'last_login_at'  => $member->last_login_at === null ? null : (string)$member->last_login_at,
            'created_at'     => (string)$member->created_at,
        ];
    }
}
