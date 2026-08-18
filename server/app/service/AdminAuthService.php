<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\AdminUser;
use app\support\Result;
use app\support\Token;

class AdminAuthService
{
    /**
     * @return array{token: string, expires_at: int, admin: array<string, mixed>}
     */
    public static function login(string $username, string $password, string $ip): array
    {
        $admin = AdminUser::query()->where('username', $username)->first();
        if ($admin === null || !password_verify($password, (string)$admin->password)) {
            throw new BusinessException('账号或密码错误');
        }
        if ((int)$admin->status !== AdminUser::STATUS_NORMAL) {
            throw new BusinessException('账号已被禁用', Result::FORBIDDEN);
        }

        $admin->last_login_at = date('Y-m-d H:i:s');
        $admin->last_login_ip = $ip;
        $admin->save();

        $token = Token::issue(Token::GUARD_ADMIN, (int)$admin->id, ['role' => (int)$admin->role]);

        return [
            'token'      => $token['token'],
            'expires_at' => $token['expires_at'],
            'admin'      => self::profile($admin),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function profile(AdminUser $admin): array
    {
        return [
            'id'        => (int)$admin->id,
            'username'  => (string)$admin->username,
            'real_name' => (string)$admin->real_name,
            'avatar'    => (string)$admin->avatar,
            'phone'     => (string)$admin->phone,
            'role'      => (int)$admin->role,
        ];
    }

    public static function changePassword(int $adminId, string $oldPassword, string $newPassword): void
    {
        $admin = AdminUser::query()->find($adminId);
        if ($admin === null) {
            throw new BusinessException('账号不存在');
        }
        if (!password_verify($oldPassword, (string)$admin->password)) {
            throw new BusinessException('原密码错误');
        }

        $admin->password = password_hash($newPassword, PASSWORD_DEFAULT);
        $admin->save();
    }
}
