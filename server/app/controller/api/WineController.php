<?php

declare(strict_types=1);

namespace app\controller\api;

use app\service\WineService;
use app\support\Result;
use support\Request;
use support\Response;

class WineController
{
    public function storages(Request $request): Response
    {
        [$page, $pageSize] = $this->pagination($request);
        $status = $request->get('status');

        $result = WineService::storages(
            (int)$request->memberId,
            $status === null || $status === '' ? null : (int)$status,
            $page,
            $pageSize
        );

        return Result::page($result['list'], $result['total'], $page, $pageSize);
    }

    public function storeCode(Request $request): Response
    {
        return Result::success(WineService::storeCode((int)$request->memberId));
    }

    public function take(Request $request, int $id): Response
    {
        $quantity = (int)$request->post('quantity', 0);

        return Result::success(WineService::take((int)$request->memberId, $id, $quantity));
    }

    public function takes(Request $request): Response
    {
        [$page, $pageSize] = $this->pagination($request);
        $status = $request->get('status');

        $result = WineService::takes(
            (int)$request->memberId,
            $status === null || $status === '' ? null : (int)$status,
            $page,
            $pageSize
        );

        return Result::page($result['list'], $result['total'], $page, $pageSize);
    }

    public function cancelTake(Request $request, int $id): Response
    {
        WineService::cancelTake((int)$request->memberId, $id);

        return Result::success(null, '已取消');
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
