<?php

declare(strict_types=1);

namespace app\middleware;

use app\exception\BusinessException;
use app\model\Member;
use app\support\Result;
use app\support\Token;
use support\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 小程序会员鉴权
 */
class MemberAuth implements MiddlewareInterface
{
    public function process(\Webman\Http\Request $request, callable $handler): Response
    {
        /** @var Request $request */
        $token = $this->bearerToken($request);
        if ($token === '') {
            throw new BusinessException('请先登录', Result::UNAUTHORIZED);
        }

        $payload  = Token::verify(Token::GUARD_MEMBER, $token);
        $memberId = (int)($payload['uid'] ?? 0);

        $member = Member::query()->find($memberId);
        if ($member === null) {
            throw new BusinessException('账号不存在', Result::UNAUTHORIZED);
        }
        if ((int)$member->status !== Member::STATUS_NORMAL) {
            throw new BusinessException('账号已被禁用', Result::FORBIDDEN);
        }

        $request->memberId = $memberId;

        return $handler($request);
    }

    private function bearerToken(\Webman\Http\Request $request): string
    {
        $header = (string)$request->header('authorization', '');
        if (stripos($header, 'Bearer ') === 0) {
            return trim(substr($header, 7));
        }
        return (string)$request->input('token', '');
    }
}
