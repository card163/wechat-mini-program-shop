<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\model\Member;
use app\model\MemberBalanceLog;
use app\service\AdminMemberService;
use app\service\MemberService;
use app\support\Result;
use support\Request;
use support\Response;

class MemberController
{
    public function index(Request $request): Response
    {
        [$page, $pageSize] = $this->pagination($request);
        $status = $request->get('status');

        $result = AdminMemberService::paginate(
            trim((string)$request->get('keyword', '')),
            $status === null || $status === '' ? null : (int)$status,
            (string)$request->get('order_by', 'id'),
            $page,
            $pageSize
        );

        return Result::page($result['list'], $result['total'], $page, $pageSize);
    }

    public function show(Request $request, int $id): Response
    {
        return Result::success(AdminMemberService::detail($id));
    }

    public function status(Request $request, int $id): Response
    {
        AdminMemberService::changeStatus($id, (int)$request->post('status', Member::STATUS_NORMAL));

        return Result::success(null, '操作成功');
    }

    public function adjustBalance(Request $request, int $id): Response
    {
        AdminMemberService::adjustBalance(
            $id,
            (int)$request->post('amount', 0),
            (string)$request->post('remark', ''),
            (int)$request->adminId
        );

        return Result::success(null, '调整成功');
    }

    public function grantGift(Request $request, int $id): Response
    {
        AdminMemberService::grantGift(
            $id,
            (int)$request->post('amount', 0),
            (int)$request->post('expire_days', 0),
            (string)$request->post('remark', '管理员发放'),
            (int)$request->adminId
        );

        return Result::success(null, '发放成功');
    }

    public function adjustPoint(Request $request, int $id): Response
    {
        AdminMemberService::adjustPoint(
            $id,
            (int)$request->post('point', 0),
            (string)$request->post('remark', ''),
            (int)$request->adminId
        );

        return Result::success(null, '调整成功');
    }

    public function balanceLogs(Request $request, int $id): Response
    {
        [$page, $pageSize] = $this->pagination($request);
        $accountType = (int)$request->get('account_type', MemberBalanceLog::ACCOUNT_PRINCIPAL);

        $result = MemberService::balanceLogs($id, $accountType, $page, $pageSize);

        return Result::page($result['list'], $result['total'], $page, $pageSize);
    }

    public function pointLogs(Request $request, int $id): Response
    {
        [$page, $pageSize] = $this->pagination($request);
        $result = MemberService::pointLogs($id, $page, $pageSize);

        return Result::page($result['list'], $result['total'], $page, $pageSize);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function pagination(Request $request): array
    {
        return [
            max(1, (int)$request->get('page', 1)),
            min(100, max(1, (int)$request->get('page_size', 20))),
        ];
    }
}
