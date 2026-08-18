<?php

declare(strict_types=1);

namespace app\controller\api;

use app\model\Banner;
use app\model\Member;
use app\service\MemberAuthService;
use app\service\RankingService;
use app\service\SettingService;
use app\support\Auth;
use app\support\Result;
use support\Request;
use support\Response;

class HomeController
{
    public function index(Request $request): Response
    {
        $memberId = Auth::optionalMemberId($request);
        $member   = $memberId > 0 ? Member::query()->find($memberId) : null;

        return Result::success([
            'shop'    => $this->shopSetting(),
            'banners' => $this->banners(),
            'member'  => $member === null ? null : MemberAuthService::profile($member),
        ]);
    }

    public function shopInfo(): Response
    {
        return Result::success($this->shopSetting());
    }

    public function ranking(Request $request): Response
    {
        $limit = (int)$request->get('limit', 50);

        return Result::success(RankingService::points($limit, Auth::optionalMemberId($request)));
    }

    /**
     * @return array<string, string>
     */
    private function shopSetting(): array
    {
        $base = SettingService::group('base');

        return [
            'name'           => $base['shop_name'] ?? '',
            'phone'          => $base['shop_phone'] ?? '',
            'address'        => $base['shop_address'] ?? '',
            'notice'         => $base['shop_notice'] ?? '',
            'business_hours' => $base['business_hours'] ?? '',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function banners(): array
    {
        return Banner::query()
            ->where('status', Banner::STATUS_ON)
            ->orderBy('sort')
            ->orderByDesc('id')
            ->get(['id', 'title', 'image', 'link'])
            ->map(static fn(Banner $banner): array => [
                'id'    => (int)$banner->id,
                'title' => (string)$banner->title,
                'image' => (string)$banner->image,
                'link'  => (string)$banner->link,
            ])
            ->all();
    }
}
