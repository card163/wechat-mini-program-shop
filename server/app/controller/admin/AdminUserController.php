<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\exception\BusinessException;
use app\model\AdminUser;
use app\service\AdminAuthService;
use app\support\Result;
use Respect\Validation\Validator as v;
use support\Request;
use support\Response;

class AdminUserController
{
    public function index(Request $request): Response
    {
        $page     = max(1, (int)$request->get('page', 1));
        $pageSize = min(100, max(1, (int)$request->get('page_size', 20)));

        $query = AdminUser::query();
        $total = (int)$query->count();
        $list  = $query->orderByDesc('id')
            ->forPage($page, $pageSize)
            ->get()
            ->map(static fn(AdminUser $admin): array => AdminAuthService::profile($admin) + [
                'status'        => (int)$admin->status,
                'last_login_at' => $admin->last_login_at === null ? null : (string)$admin->last_login_at,
                'created_at'    => (string)$admin->created_at,
            ])
            ->all();

        return Result::page($list, $total, $page, $pageSize);
    }

    public function store(Request $request): Response
    {
        $username = trim((string)$request->post('username', ''));
        $password = (string)$request->post('password', '');

        v::stringType()->length(3, 32)->setTemplate('账号长度需为 3-32 位')->assert($username);
        v::stringType()->length(6, 32)->setTemplate('密码长度需为 6-32 位')->assert($password);

        if (AdminUser::query()->where('username', $username)->exists()) {
            throw new BusinessException('该账号已存在');
        }

        $admin = new AdminUser();
        $admin->username  = $username;
        $admin->password  = password_hash($password, PASSWORD_DEFAULT);
        $admin->real_name = (string)$request->post('real_name', '');
        $admin->phone     = (string)$request->post('phone', '');
        $admin->role      = (int)$request->post('role', AdminUser::ROLE_STAFF) === AdminUser::ROLE_SUPER
            ? AdminUser::ROLE_SUPER
            : AdminUser::ROLE_STAFF;
        $admin->status    = AdminUser::STATUS_NORMAL;
        $admin->save();

        return Result::success(AdminAuthService::profile($admin), '创建成功');
    }

    public function update(Request $request, int $id): Response
    {
        $admin = $this->find($id);

        if (($realName = $request->post('real_name')) !== null) {
            $admin->real_name = (string)$realName;
        }
        if (($phone = $request->post('phone')) !== null) {
            $admin->phone = (string)$phone;
        }
        if (($role = $request->post('role')) !== null) {
            $admin->role = (int)$role === AdminUser::ROLE_SUPER ? AdminUser::ROLE_SUPER : AdminUser::ROLE_STAFF;
        }
        if (($status = $request->post('status')) !== null) {
            $admin->status = (int)$status === AdminUser::STATUS_NORMAL ? AdminUser::STATUS_NORMAL : AdminUser::STATUS_DISABLED;
        }
        if (($password = $request->post('password')) !== null && $password !== '') {
            v::stringType()->length(6, 32)->setTemplate('密码长度需为 6-32 位')->assert((string)$password);
            $admin->password = password_hash((string)$password, PASSWORD_DEFAULT);
        }

        $admin->save();

        return Result::success(AdminAuthService::profile($admin), '保存成功');
    }

    public function destroy(Request $request, int $id): Response
    {
        if ($id === (int)$request->adminId) {
            throw new BusinessException('不能删除当前登录账号');
        }

        $this->find($id)->delete();

        return Result::success(null, '删除成功');
    }

    private function find(int $id): AdminUser
    {
        $admin = AdminUser::query()->find($id);
        if ($admin === null) {
            throw new BusinessException('账号不存在', Result::NOT_FOUND);
        }

        return $admin;
    }
}
