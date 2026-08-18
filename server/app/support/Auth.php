<?php

declare(strict_types=1);

namespace app\support;

use Throwable;
use Webman\Http\Request;

/**
 * 可选鉴权：接口本身允许匿名访问，但携带合法 token 时返回登录态
 */
class Auth
{
    public static function optionalMemberId(Request $request): int
    {
        $header = (string)$request->header('authorization', '');
        $token  = stripos($header, 'Bearer ') === 0 ? trim(substr($header, 7)) : (string)$request->input('token', '');
        if ($token === '') {
            return 0;
        }

        try {
            $payload = Token::verify(Token::GUARD_MEMBER, $token);
        } catch (Throwable) {
            return 0;
        }

        return (int)($payload['uid'] ?? 0);
    }
}
