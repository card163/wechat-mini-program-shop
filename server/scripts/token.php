<?php

declare(strict_types=1);

/**
 * 本地联调工具：生成会员 / 管理端 token
 *
 * 用法：
 *   php scripts/token.php member 1
 *   php scripts/token.php admin 1
 *
 * 仅供本地开发使用，需要服务器 shell 权限，不对外暴露 HTTP 接口。
 */

use app\support\Token;

chdir(dirname(__DIR__));
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/support/bootstrap.php';

$guard = $argv[1] ?? Token::GUARD_MEMBER;
$uid   = (int)($argv[2] ?? 0);

if (!in_array($guard, [Token::GUARD_MEMBER, Token::GUARD_ADMIN], true) || $uid <= 0) {
    fwrite(STDERR, "用法: php scripts/token.php <member|admin> <id>\n");
    exit(1);
}

echo Token::issue($guard, $uid)['token'], PHP_EOL;
