<?php

declare(strict_types=1);

namespace app\controller\api;

use app\service\OrderService;
use app\support\Result;
use Respect\Validation\Validator as v;
use support\Request;
use support\Response;

class OrderController
{
    public function preview(Request $request): Response
    {
        return Result::success(OrderService::preview((int)$request->memberId, $this->items($request)));
    }

    public function create(Request $request): Response
    {
        $tableId = (int)$request->post('table_id', 0);
        $payType = (int)$request->post('pay_type', 0);
        $remark  = (string)$request->post('remark', '');

        v::intVal()->positive()->setTemplate('请选择桌号')->assert($tableId);

        $result = OrderService::create((int)$request->memberId, $this->items($request), $tableId, $payType, $remark);

        return Result::success($result);
    }

    public function pay(Request $request, int $id): Response
    {
        $payType = (int)$request->post('pay_type', 0);

        return Result::success(OrderService::pay((int)$request->memberId, $id, $payType));
    }

    public function index(Request $request): Response
    {
        $status   = $request->get('status');
        $page     = max(1, (int)$request->get('page', 1));
        $pageSize = min(100, max(1, (int)$request->get('page_size', 20)));

        $result = OrderService::paginate(
            (int)$request->memberId,
            $status === null || $status === '' ? null : (int)$status,
            $page,
            $pageSize
        );

        return Result::page($result['list'], $result['total'], $page, $pageSize);
    }

    public function detail(Request $request, int $id): Response
    {
        return Result::success(OrderService::detail((int)$request->memberId, $id));
    }

    public function cancel(Request $request, int $id): Response
    {
        OrderService::cancel((int)$request->memberId, $id);

        return Result::success(null, '订单已取消');
    }

    /**
     * 兼容 JSON 字符串与表单数组两种提交方式
     *
     * @return array<int, array{goods_id: int|string, quantity: int|string}>
     */
    private function items(Request $request): array
    {
        $items = $request->post('items', []);
        if (is_string($items)) {
            $items = json_decode($items, true);
        }

        return is_array($items) ? $items : [];
    }
}
