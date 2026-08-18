<?php

declare(strict_types=1);

namespace app\middleware;

use app\exception\BusinessException;
use app\model\AdminUser;
use app\support\Result;
use app\support\Token;
use support\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 管理后台 / 店员鉴权
 */
class AdminAuth implements MiddlewareInterface
{
    public function process(\Webman\Http\Request $request, callable $handler): Response
    {
        /** @var Request $request */
        $token = $this->bearerToken($request);
        if ($token === '') {
            throw new BusinessException('请先登录', Result::UNAUTHORIZED);
        }

        $payload = Token::verify(Token::GUARD_ADMIN, $token);
        $adminId = (int)($payload['uid'] ?? 0);

        $admin = AdminUser::query()->find($adminId);
        if ($admin === null) {
            throw new BusinessException('账号不存在', Result::UNAUTHORIZED);
        }
        if ((int)$admin->status !== AdminUser::STATUS_NORMAL) {
            throw new BusinessException('账号已被禁用', Result::FORBIDDEN);
        }

        $request->adminId   = $adminId;
        $request->adminRole = (int)$admin->role;

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
