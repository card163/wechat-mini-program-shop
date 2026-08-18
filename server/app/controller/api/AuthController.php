<?php

declare(strict_types=1);

namespace app\controller\api;

use app\model\Member;
use app\service\MemberAuthService;
use app\support\Result;
use Respect\Validation\Validator as v;
use support\Request;
use support\Response;

class AuthController
{
    public function login(Request $request): Response
    {
        $code = trim((string)$request->post('code', ''));
        v::stringType()->notEmpty()->setTemplate('缺少登录凭证')->assert($code);

        return Result::success(MemberAuthService::login($code));
    }

    public function profile(Request $request): Response
    {
        $nickname = $request->post('nickname');
        $avatar   = $request->post('avatar');

        $member = MemberAuthService::updateProfile(
            (int)$request->memberId,
            $nickname === null ? null : (string)$nickname,
            $avatar === null ? null : (string)$avatar,
        );

        return Result::success(MemberAuthService::profile($member));
    }

    public function phone(Request $request): Response
    {
        $code = trim((string)$request->post('code', ''));
        v::stringType()->notEmpty()->setTemplate('缺少手机号凭证')->assert($code);

        $phone = MemberAuthService::bindPhone((int)$request->memberId, $code);

        return Result::success(['phone' => MemberAuthService::maskPhone($phone)]);
    }

    public function logout(Request $request): Response
    {
        Member::query()->whereKey((int)$request->memberId)->update(['updated_at' => date('Y-m-d H:i:s')]);

        return Result::success();
    }
}
