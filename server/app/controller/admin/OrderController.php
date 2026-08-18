<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\service\AdminOrderService;
use app\support\Result;
use support\Request;
use support\Response;

class OrderController
{
    public function index(Request $request): Response
    {
        $page     = max(1, (int)$request->get('page', 1));
        $pageSize = min(100, max(1, (int)$request->get('page_size', 20)));

        $result = AdminOrderService::paginate([
            'order_no'     => $request->get('order_no'),
            'order_status' => $request->get('order_status'),
            'table_id'     => $request->get('table_id'),
            'member_id'    => $request->get('member_id'),
            'start_date'   => $request->get('start_date'),
            'end_date'     => $request->get('end_date'),
        ], $page, $pageSize);

        return Result::page($result['list'], $result['total'], $page, $pageSize);
    }

    public function show(Request $request, int $id): Response
    {
        return Result::success(AdminOrderService::detail($id));
    }

    public function finish(Request $request, int $id): Response
    {
        AdminOrderService::finish($id);

        return Result::success(null, '订单已完成');
    }

    public function refund(Request $request, int $id): Response
    {
        AdminOrderService::refund($id, (string)$request->post('remark', ''), (int)$request->adminId);

        return Result::success(null, '退款成功');
    }
}
