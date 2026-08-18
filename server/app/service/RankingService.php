<?php

declare(strict_types=1);

namespace app\service;

use app\model\Member;

class RankingService
{
    /**
     * 按累计记分牌排行
     *
     * @return array{list: array<int, array<string, mixed>>, me: array<string, mixed>|null}
     */
    public static function points(int $limit, int $memberId): array
    {
        $limit = max(1, min($limit, 100));

        $list = Member::query()
            ->where('status', Member::STATUS_NORMAL)
            ->where('total_point', '>', 0)
            ->orderByDesc('total_point')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'nickname', 'avatar', 'total_point'])
            ->values()
            ->map(static fn(Member $member, int $index): array => [
                'rank'        => $index + 1,
                'member_id'   => (int)$member->id,
                'nickname'    => MemberAuthService::displayName((string)$member->nickname),
                'avatar'      => (string)$member->avatar,
                'total_point' => (int)$member->total_point,
            ])
            ->all();

        $me = null;
        if ($memberId > 0) {
            $member = Member::query()->find($memberId);
            if ($member !== null) {
                $totalPoint = (int)$member->total_point;
                $rank = $totalPoint > 0
                    ? (int)Member::query()
                        ->where('status', Member::STATUS_NORMAL)
                        ->where('total_point', '>', $totalPoint)
                        ->count() + 1
                    : 0;

                $me = [
                    'rank'        => $rank,
                    'member_id'   => (int)$member->id,
                    'nickname'    => MemberAuthService::displayName((string)$member->nickname),
                    'avatar'      => (string)$member->avatar,
                    'total_point' => $totalPoint,
                ];
            }
        }

        return ['list' => $list, 'me' => $me];
    }
}
