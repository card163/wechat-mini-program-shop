<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\model\AdminUser;
use app\service\AdminAuthService;
use app\support\Result;
use Respect\Validation\Validator as v;
use support\Request;
use support\Response;

class AuthController
{
    public function login(Request $request): Response
    {
        $username = trim((string)$request->post('username', ''));
        $password = (string)$request->post('password', '');

        v::stringType()->notEmpty()->setTemplate('请输入账号')->assert($username);
        v::stringType()->notEmpty()->setTemplate('请输入密码')->assert($password);

        $data = AdminAuthService::login($username, $password, $request->getRealIp());

        return Result::success($data);
    }

    public function profile(Request $request): Response
    {
        $admin = AdminUser::query()->findOrFail((int)$request->adminId);

        return Result::success(AdminAuthService::profile($admin));
    }

    public function changePassword(Request $request): Response
    {
        $oldPassword = (string)$request->post('old_password', '');
        $newPassword = (string)$request->post('new_password', '');

        v::stringType()->notEmpty()->setTemplate('请输入原密码')->assert($oldPassword);
        v::stringType()->length(6, 32)->setTemplate('新密码长度需为 6-32 位')->assert($newPassword);

        AdminAuthService::changePassword((int)$request->adminId, $oldPassword, $newPassword);

        return Result::success(null, '密码修改成功');
    }
}
