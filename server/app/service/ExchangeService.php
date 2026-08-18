<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\ExchangeGoods;
use app\model\ExchangeRecord;
use app\model\Member;
use app\model\MemberGiftBatch;
use app\model\MemberPointLog;
use app\support\Result;
use app\support\Sn;
use Illuminate\Database\Capsule\Manager as Db;

class ExchangeService
{
    /**
     * 按数量取积分：将任意数量积分按配置比例折算为赠金，多余不足一份的积分不扣除
     *
     * @return array<string, mixed>
     */
    public static function exchangeByPoint(int $memberId, int $point): array
    {
        if ($point <= 0) {
            throw new BusinessException('请输入取积分数量');
        }

        $rate = max(1, SettingService::int('point', 'point_to_gift_rate', 300));

        return Db::connection()->transaction(static function () use ($memberId, $point, $rate): array {
            $member = AccountService::lockMember($memberId);

            $consumePoint = intdiv($point, $rate) * $rate;
            if ($consumePoint <= 0) {
                throw new BusinessException("最少需要 {$rate} 积分才能兑换");
            }
            if ((int)$member->point < $consumePoint) {
                throw new BusinessException('积分不足');
            }

            $giftAmount = intdiv($consumePoint, $rate) * 100;

            AccountService::changePoint($member, -$consumePoint, MemberPointLog::BIZ_EXCHANGE_GIFT, 0, '取积分兑换赠金');

            $batch = AccountService::grantGift(
                $member,
                $giftAmount,
                MemberGiftBatch::SOURCE_EXCHANGE,
                0,
                SettingService::int('point', 'gift_default_days', 0),
                '',
                '取积分兑换赠金'
            );

            return [
                'consume_point' => $consumePoint,
                'gift_amount'   => $giftAmount,
                'point_left'    => (int)$member->point,
                'gift_balance'  => (int)$member->gift_balance,
                'batch_id'      => (int)$batch->id,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function goodsList(int $memberId): array
    {
        $list = ExchangeGoods::query()
            ->where('status', ExchangeGoods::STATUS_ON)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(static fn(ExchangeGoods $goods): array => [
                'id'               => (int)$goods->id,
                'type'             => (int)$goods->type,
                'name'             => (string)$goods->name,
                'cover'            => (string)$goods->cover,
                'point'            => (int)$goods->point,
                'gift_amount'      => (int)$goods->gift_amount,
                'gift_expire_days' => (int)$goods->gift_expire_days,
                'stock'            => (int)$goods->stock,
                'description'      => (string)$goods->description,
            ])
            ->all();

        $point = 0;
        if ($memberId > 0) {
            $point = (int)(Member::query()->where('id', $memberId)->value('point') ?? 0);
        }

        return [
            'point' => $point,
            'rate'  => SettingService::int('point', 'point_to_gift_rate', 300),
            'list'  => $list,
        ];
    }

    /**
     * 提交兑换
     *
     * @return array<string, mixed>
     */
    public static function exchange(int $memberId, int $goodsId): array
    {
        return Db::connection()->transaction(static function () use ($memberId, $goodsId): array {
            $goods = ExchangeGoods::query()
                ->where('status', ExchangeGoods::STATUS_ON)
                ->lockForUpdate()
                ->find($goodsId);

            if ($goods === null) {
                throw new BusinessException('兑换商品不存在');
            }
            if ((int)$goods->stock !== ExchangeGoods::STOCK_UNLIMITED && (int)$goods->stock <= 0) {
                throw new BusinessException('该商品已兑完');
            }

            $member = AccountService::lockMember($memberId);
            if ((int)$member->point < (int)$goods->point) {
                throw new BusinessException('记分牌不足');
            }

            $record = new ExchangeRecord();
            $record->record_no   = Sn::make(Sn::EXCHANGE);
            $record->member_id   = $memberId;
            $record->goods_id    = (int)$goods->id;
            $record->goods_name  = (string)$goods->name;
            $record->type        = (int)$goods->type;
            $record->point       = (int)$goods->point;
            $record->gift_amount = (int)$goods->gift_amount;
            $record->status      = ExchangeRecord::STATUS_PENDING;
            $record->save();

            AccountService::changePoint(
                $member,
                -(int)$goods->point,
                (int)$goods->type === ExchangeGoods::TYPE_GIFT ? MemberPointLog::BIZ_EXCHANGE_GIFT : MemberPointLog::BIZ_EXCHANGE_GOODS,
                (int)$record->id,
                '兑换' . (string)$goods->name
            );

            if ((int)$goods->stock !== ExchangeGoods::STOCK_UNLIMITED) {
                $goods->stock = (int)$goods->stock - 1;
            }
            $goods->exchanged = (int)$goods->exchanged + 1;
            $goods->save();

            // 赠金类兑换立即到账并核销，实物类等待店员核销
            if ((int)$goods->type === ExchangeGoods::TYPE_GIFT) {
                AccountService::grantGift(
                    $member,
                    (int)$goods->gift_amount,
                    MemberGiftBatch::SOURCE_EXCHANGE,
                    (int)$record->id,
                    (int)$goods->gift_expire_days,
                    (string)$record->record_no,
                    '记分牌兑换赠金'
                );

                $record->status      = ExchangeRecord::STATUS_VERIFIED;
                $record->verified_at = date('Y-m-d H:i:s');
                $record->save();
            }

            return [
                'record_id'    => (int)$record->id,
                'record_no'    => (string)$record->record_no,
                'type'         => (int)$record->type,
                'point'        => (int)$record->point,
                'gift_amount'  => (int)$record->gift_amount,
                'status'       => (int)$record->status,
                'point_left'   => (int)$member->point,
                'gift_balance' => (int)$member->gift_balance,
            ];
        });
    }

    /**
     * @return array{list: array<int, array<string, mixed>>, total: int}
     */
    public static function records(int $memberId, ?int $status, int $page, int $pageSize): array
    {
        $query = ExchangeRecord::query()->where('member_id', $memberId);
        if ($status !== null) {
            $query->where('status', $status);
        }

        $total = (int)$query->count();
        $list  = $query->orderByDesc('id')
            ->forPage($page, $pageSize)
            ->get()
            ->map(static fn(ExchangeRecord $record): array => self::format($record))
            ->all();

        return ['list' => $list, 'total' => $total];
    }

    /**
     * @return array<string, mixed>
     */
    public static function code(int $memberId, int $recordId): array
    {
        $record = ExchangeRecord::query()->where('member_id', $memberId)->find($recordId);
        if ($record === null) {
            throw new BusinessException('兑换记录不存在', Result::NOT_FOUND);
        }
        if ((int)$record->status === ExchangeRecord::STATUS_VERIFIED) {
            throw new BusinessException('该兑换已核销');
        }
        if ((int)$record->status === ExchangeRecord::STATUS_CANCELLED) {
            throw new BusinessException('该兑换已取消');
        }

        return [
            'record_no'  => (string)$record->record_no,
            'goods_name' => (string)$record->goods_name,
            'status'     => (int)$record->status,
        ];
    }

    /**
     * 店员核销实物兑换
     *
     * @return array<string, mixed>
     */
    public static function verify(string $recordNo, int $adminId): array
    {
        return Db::connection()->transaction(static function () use ($recordNo, $adminId): array {
            $record = ExchangeRecord::query()->where('record_no', $recordNo)->lockForUpdate()->first();
            if ($record === null) {
                throw new BusinessException('兑换码无效');
            }
            if ((int)$record->status === ExchangeRecord::STATUS_VERIFIED) {
                throw new BusinessException('该兑换已核销');
            }
            if ((int)$record->status === ExchangeRecord::STATUS_CANCELLED) {
                throw new BusinessException('该兑换已取消');
            }

            $record->status          = ExchangeRecord::STATUS_VERIFIED;
            $record->verify_admin_id = $adminId;
            $record->verified_at     = date('Y-m-d H:i:s');
            $record->save();

            return self::format($record);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function format(ExchangeRecord $record): array
    {
        return [
            'id'          => (int)$record->id,
            'record_no'   => (string)$record->record_no,
            'member_id'   => (int)$record->member_id,
            'goods_name'  => (string)$record->goods_name,
            'type'        => (int)$record->type,
            'point'       => (int)$record->point,
            'gift_amount' => (int)$record->gift_amount,
            'status'      => (int)$record->status,
            'status_text' => match ((int)$record->status) {
                ExchangeRecord::STATUS_PENDING   => '待核销',
                ExchangeRecord::STATUS_VERIFIED  => '已核销',
                ExchangeRecord::STATUS_CANCELLED => '已取消',
                default                          => '未知',
            },
            'verified_at' => $record->verified_at === null ? null : (string)$record->verified_at,
            'created_at'  => (string)$record->created_at,
        ];
    }
}
