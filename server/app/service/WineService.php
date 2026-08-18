<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\Member;
use app\model\WineStorage;
use app\model\WineTakeRecord;
use app\support\Result;
use app\support\Scene;
use app\support\Sn;
use Illuminate\Database\Capsule\Manager as Db;
use Throwable;

class WineService
{
    /**
     * @param int $memberId 0 表示不限会员（后台使用）
     * @return array{list: array<int, array<string, mixed>>, total: int}
     */
    public static function storages(int $memberId, ?int $status, int $page, int $pageSize): array
    {
        $query = WineStorage::query();
        if ($memberId > 0) {
            $query->where('member_id', $memberId);
        }
        if ($status !== null) {
            $query->where('status', $status);
        }

        $total = (int)$query->count();
        $list  = $query->orderByDesc('id')
            ->forPage($page, $pageSize)
            ->get()
            ->map(static fn(WineStorage $storage): array => self::formatStorage($storage))
            ->all();

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 会员存酒码，供店员扫码登记
     *
     * @return array<string, mixed>
     */
    public static function storeCode(int $memberId): array
    {
        $ttl   = max(60, SettingService::int('wine', 'take_code_expire_min', 10) * 60);
        $scene = Scene::make($memberId, $ttl);

        // 未配置微信凭证时返回空图，前端可用 scene 本地渲染普通二维码兜底
        $qrcode = '';
        try {
            $qrcode = WechatService::getUnlimitedQrCode($scene['scene'], 'pages/wine/scan');
        } catch (Throwable) {
        }

        return [
            'scene'      => $scene['scene'],
            'qrcode'     => $qrcode,
            'expires_at' => $scene['expires_at'],
        ];
    }

    /**
     * 发起取酒，生成待核销取酒码
     *
     * @return array<string, mixed>
     */
    public static function take(int $memberId, int $storageId, int $quantity): array
    {
        return Db::connection()->transaction(static function () use ($memberId, $storageId, $quantity): array {
            $storage = WineStorage::query()->where('member_id', $memberId)->lockForUpdate()->find($storageId);
            if ($storage === null) {
                throw new BusinessException('存酒记录不存在', Result::NOT_FOUND);
            }
            if ((int)$storage->status === WineStorage::STATUS_EXPIRED) {
                throw new BusinessException('该存酒已过期，请联系店员');
            }
            if ((int)$storage->status !== WineStorage::STATUS_STORING) {
                throw new BusinessException('该存酒已取完');
            }
            if ($quantity <= 0 || $quantity > (int)$storage->remain_qty) {
                throw new BusinessException($quantity <= 0 ? '取酒数量不正确' : '剩余数量不足');
            }

            $pending = WineTakeRecord::query()
                ->where('storage_id', $storageId)
                ->where('status', WineTakeRecord::STATUS_PENDING)
                ->where('code_expired_at', '>', date('Y-m-d H:i:s'))
                ->exists();
            if ($pending) {
                throw new BusinessException('已有待核销的取酒码，请先使用');
            }

            $minutes = max(1, SettingService::int('wine', 'take_code_expire_min', 10));

            $record = new WineTakeRecord();
            $record->take_no         = Sn::make(Sn::WINE_TAKE);
            $record->storage_id      = $storageId;
            $record->member_id       = $memberId;
            $record->quantity        = $quantity;
            $record->status          = WineTakeRecord::STATUS_PENDING;
            $record->code_expired_at = date('Y-m-d H:i:s', time() + $minutes * 60);
            $record->save();

            return [
                'take_id'         => (int)$record->id,
                'take_no'         => (string)$record->take_no,
                'wine_name'       => (string)$storage->wine_name,
                'quantity'        => $quantity,
                'status'          => (int)$record->status,
                'code_expired_at' => (string)$record->code_expired_at,
            ];
        });
    }

    /**
     * @return array{list: array<int, array<string, mixed>>, total: int}
     */
    public static function takes(int $memberId, ?int $status, int $page, int $pageSize): array
    {
        $query = WineTakeRecord::query()->where('member_id', $memberId);
        if ($status !== null) {
            $query->where('status', $status);
        }

        $total   = (int)$query->count();
        $records = $query->orderByDesc('id')->forPage($page, $pageSize)->get();
        $names   = WineStorage::query()
            ->whereIn('id', $records->pluck('storage_id')->all())
            ->pluck('wine_name', 'id');

        $list = $records->map(static fn(WineTakeRecord $record): array => self::formatTake($record, (string)($names[$record->storage_id] ?? '')))->all();

        return ['list' => $list, 'total' => $total];
    }

    public static function cancelTake(int $memberId, int $takeId): void
    {
        Db::connection()->transaction(static function () use ($memberId, $takeId): void {
            $record = WineTakeRecord::query()->where('member_id', $memberId)->lockForUpdate()->find($takeId);
            if ($record === null) {
                throw new BusinessException('取酒记录不存在', Result::NOT_FOUND);
            }
            if ((int)$record->status === WineTakeRecord::STATUS_VERIFIED) {
                throw new BusinessException('该取酒码已核销');
            }

            $record->status = WineTakeRecord::STATUS_INVALID;
            $record->save();
        });
    }

    /**
     * 店员扫存酒码，解析会员
     *
     * @return array<string, mixed>
     */
    public static function scan(string $scene): array
    {
        $ttl      = max(60, SettingService::int('wine', 'take_code_expire_min', 10) * 60);
        $memberId = Scene::parse($scene, $ttl);

        $member = Member::query()->find($memberId);
        if ($member === null) {
            throw new BusinessException('会员不存在');
        }

        return [
            'member' => [
                'id'       => (int)$member->id,
                'nickname' => MemberAuthService::displayName((string)$member->nickname),
                'avatar'   => (string)$member->avatar,
                'phone'    => MemberAuthService::maskPhone((string)$member->phone),
            ],
        ];
    }

    /**
     * 店员登记存酒
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function createStorage(array $data, int $adminId): array
    {
        $member = Member::query()->find((int)$data['member_id']);
        if ($member === null) {
            throw new BusinessException('会员不存在');
        }

        $quantity = (int)$data['total_qty'];
        if ($quantity <= 0 || $quantity > 99) {
            throw new BusinessException('存酒数量不正确');
        }

        $expireDays = (int)($data['expire_days'] ?? SettingService::int('wine', 'default_expire_days', 90));

        $storage = new WineStorage();
        $storage->member_id      = (int)$member->id;
        $storage->wine_name      = mb_substr((string)$data['wine_name'], 0, 64);
        $storage->spec           = mb_substr((string)($data['spec'] ?? ''), 0, 64);
        $storage->unit           = mb_substr((string)($data['unit'] ?? '瓶'), 0, 16);
        $storage->total_qty      = $quantity;
        $storage->remain_qty     = $quantity;
        $storage->images         = $data['images'] ?? [];
        $storage->status         = WineStorage::STATUS_STORING;
        $storage->stored_at      = date('Y-m-d H:i:s');
        $storage->expired_at     = $expireDays > 0 ? date('Y-m-d H:i:s', strtotime("+$expireDays days")) : null;
        $storage->store_admin_id = $adminId;
        $storage->remark         = mb_substr((string)($data['remark'] ?? ''), 0, 255);
        $storage->save();

        return self::formatStorage($storage);
    }

    /**
     * 店员核销取酒码
     *
     * @return array<string, mixed>
     */
    public static function verifyTake(string $takeNo, int $adminId): array
    {
        return Db::connection()->transaction(static function () use ($takeNo, $adminId): array {
            $record = WineTakeRecord::query()->where('take_no', $takeNo)->lockForUpdate()->first();
            if ($record === null) {
                throw new BusinessException('取酒码无效');
            }
            if ((int)$record->status === WineTakeRecord::STATUS_VERIFIED) {
                throw new BusinessException('该取酒码已核销');
            }
            if ((int)$record->status === WineTakeRecord::STATUS_INVALID) {
                throw new BusinessException('该取酒码已失效');
            }
            if ($record->code_expired_at !== null && strtotime((string)$record->code_expired_at) < time()) {
                throw new BusinessException('取酒码已过期');
            }

            $storage = WineStorage::query()->lockForUpdate()->find((int)$record->storage_id);
            if ($storage === null) {
                throw new BusinessException('存酒记录不存在');
            }
            if ((int)$storage->remain_qty < (int)$record->quantity) {
                throw new BusinessException('剩余数量不足');
            }

            $storage->remain_qty = (int)$storage->remain_qty - (int)$record->quantity;
            if ($storage->remain_qty === 0) {
                $storage->status = WineStorage::STATUS_TAKEN;
            }
            $storage->save();

            $record->status          = WineTakeRecord::STATUS_VERIFIED;
            $record->verify_admin_id = $adminId;
            $record->verified_at     = date('Y-m-d H:i:s');
            $record->save();

            return self::formatTake($record, (string)$storage->wine_name) + [
                'remain_qty' => (int)$storage->remain_qty,
            ];
        });
    }

    /**
     * 定时任务：过期存酒置为已过期，同时作废其待核销取酒码
     */
    public static function expireStorages(): int
    {
        $now = date('Y-m-d H:i:s');

        $ids = WineStorage::query()
            ->where('status', WineStorage::STATUS_STORING)
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', $now)
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return 0;
        }

        WineStorage::query()->whereIn('id', $ids)->update(['status' => WineStorage::STATUS_EXPIRED]);
        WineTakeRecord::query()
            ->whereIn('storage_id', $ids)
            ->where('status', WineTakeRecord::STATUS_PENDING)
            ->update(['status' => WineTakeRecord::STATUS_INVALID]);

        return count($ids);
    }

    /**
     * @return array<string, mixed>
     */
    public static function formatStorage(WineStorage $storage): array
    {
        return [
            'id'          => (int)$storage->id,
            'member_id'   => (int)$storage->member_id,
            'wine_name'   => (string)$storage->wine_name,
            'spec'        => (string)$storage->spec,
            'unit'        => (string)$storage->unit,
            'total_qty'   => (int)$storage->total_qty,
            'remain_qty'  => (int)$storage->remain_qty,
            'images'      => (array)($storage->images ?? []),
            'status'      => (int)$storage->status,
            'status_text' => match ((int)$storage->status) {
                WineStorage::STATUS_STORING => '存放中',
                WineStorage::STATUS_TAKEN   => '已取完',
                WineStorage::STATUS_EXPIRED => '已过期',
                default                     => '未知',
            },
            'stored_at'   => (string)$storage->stored_at,
            'expired_at'  => $storage->expired_at === null ? null : (string)$storage->expired_at,
            'remark'      => (string)$storage->remark,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function formatTake(WineTakeRecord $record, string $wineName): array
    {
        return [
            'id'          => (int)$record->id,
            'take_no'     => (string)$record->take_no,
            'storage_id'  => (int)$record->storage_id,
            'member_id'   => (int)$record->member_id,
            'wine_name'   => $wineName,
            'quantity'    => (int)$record->quantity,
            'status'      => (int)$record->status,
            'status_text' => match ((int)$record->status) {
                WineTakeRecord::STATUS_PENDING  => '待核销',
                WineTakeRecord::STATUS_VERIFIED => '已核销',
                WineTakeRecord::STATUS_INVALID  => '已失效',
                default                         => '未知',
            },
            'verified_at' => $record->verified_at === null ? null : (string)$record->verified_at,
            'created_at'  => (string)$record->created_at,
        ];
    }
}
