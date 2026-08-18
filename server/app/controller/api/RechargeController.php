<?php

declare(strict_types=1);

namespace app\controller\api;

use app\service\RechargeService;
use app\support\Result;
use Respect\Validation\Validator as v;
use support\Request;
use support\Response;

class RechargeController
{
    public function packages(): Response
    {
        return Result::success(RechargeService::packages());
    }

    public function create(Request $request): Response
    {
        $packageId = (int)$request->post('package_id', 0);
        v::intVal()->positive()->setTemplate('请选择充值套餐')->assert($packageId);

        return Result::success(RechargeService::create((int)$request->memberId, $packageId));
    }

    public function detail(Request $request, int $id): Response
    {
        return Result::success(RechargeService::detail((int)$request->memberId, $id));
    }

    public function index(Request $request): Response
    {
        $page     = max(1, (int)$request->get('page', 1));
        $pageSize = min(100, max(1, (int)$request->get('page_size', 20)));

        $result = RechargeService::paginate((int)$request->memberId, $page, $pageSize);

        return Result::page($result['list'], $result['total'], $page, $pageSize);
    }
}
