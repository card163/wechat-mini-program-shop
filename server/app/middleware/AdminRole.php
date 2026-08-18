<?php

declare(strict_types=1);

namespace app\middleware;

use app\exception\BusinessException;
use app\model\AdminUser;
use app\support\Result;
use support\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 仅超级管理员可访问，必须放在 AdminAuth 之后
 */
class AdminRole implements MiddlewareInterface
{
    public function process(\Webman\Http\Request $request, callable $handler): Response
    {
        /** @var Request $request */
        if ((int)$request->adminRole !== AdminUser::ROLE_SUPER) {
            throw new BusinessException('无操作权限', Result::FORBIDDEN);
        }

        return $handler($request);
    }
}
