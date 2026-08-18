<?php

declare(strict_types=1);

namespace app\controller\api;

use app\service\ExchangeService;
use app\support\Auth;
use app\support\Result;
use Respect\Validation\Validator as v;
use support\Request;
use support\Response;

class ExchangeController
{
    public function goods(Request $request): Response
    {
        return Result::success(ExchangeService::goodsList(Auth::optionalMemberId($request)));
    }

    public function exchange(Request $request): Response
    {
        $goodsId = (int)$request->post('goods_id', 0);
        v::intVal()->positive()->setTemplate('请选择兑换商品')->assert($goodsId);

        $result = ExchangeService::exchange((int)$request->memberId, $goodsId);
        $msg    = $result['status'] === 1 ? '兑换成功' : '兑换成功，请到吧台出示兑换码';

        return Result::success($result, $msg);
    }

    public function records(Request $request): Response
    {
        $status   = $request->get('status');
        $page     = max(1, (int)$request->get('page', 1));
        $pageSize = min(100, max(1, (int)$request->get('page_size', 20)));

        $result = ExchangeService::records(
            (int)$request->memberId,
            $status === null || $status === '' ? null : (int)$status,
            $page,
            $pageSize
        );

        return Result::page($result['list'], $result['total'], $page, $pageSize);
    }

    public function code(Request $request, int $id): Response
    {
        return Result::success(ExchangeService::code((int)$request->memberId, $id));
    }
}
