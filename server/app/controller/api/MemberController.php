<?php

declare(strict_types=1);

namespace app\controller\api;

use app\model\MemberBalanceLog;
use app\service\MemberService;
use app\support\Result;
use support\Request;
use support\Response;

class MemberController
{
    public function info(Request $request): Response
    {
        return Result::success(MemberService::info((int)$request->memberId));
    }

    public function balanceLogs(Request $request): Response
    {
        [$page, $pageSize] = $this->pagination($request);
        $result = MemberService::balanceLogs((int)$request->memberId, MemberBalanceLog::ACCOUNT_PRINCIPAL, $page, $pageSize);

        return Result::page($result['list'], $result['total'], $page, $pageSize);
    }

    public function giftLogs(Request $request): Response
    {
        [$page, $pageSize] = $this->pagination($request);
        $result = MemberService::balanceLogs((int)$request->memberId, MemberBalanceLog::ACCOUNT_GIFT, $page, $pageSize);

        return Result::page($result['list'], $result['total'], $page, $pageSize);
    }

    public function pointLogs(Request $request): Response
    {
        [$page, $pageSize] = $this->pagination($request);
        $result = MemberService::pointLogs((int)$request->memberId, $page, $pageSize);

        return Result::page($result['list'], $result['total'], $page, $pageSize);
    }

    public function giftBatches(Request $request): Response
    {
        [$page, $pageSize] = $this->pagination($request);
        $status = $request->get('status');
        $result = MemberService::giftBatches(
            (int)$request->memberId,
            $status === null || $status === '' ? null : (int)$status,
            $page,
            $pageSize
        );

        return Result::success([
            'summary'   => $result['summary'],
            'list'      => $result['list'],
            'total'     => $result['total'],
            'page'      => $page,
            'page_size' => $pageSize,
        ]);
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
