<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\model\ExchangeRecord;
use app\service\ExchangeService;
use app\service\WineService;
use app\support\Result;
use Respect\Validation\Validator as v;
use support\Request;
use support\Response;

/**
 * 店员核销：扫码、登记存酒、核销取酒与兑换
 */
class VerifyController
{
    public function scanWineScene(Request $request): Response
    {
        $scene = trim((string)$request->post('scene', ''));
        v::stringType()->notEmpty()->setTemplate('缺少存酒码')->assert($scene);

        return Result::success(WineService::scan($scene));
    }

    public function storeWine(Request $request): Response
    {
        $data = [
            'member_id'   => (int)$request->post('member_id', 0),
            'wine_name'   => trim((string)$request->post('wine_name', '')),
            'spec'        => (string)$request->post('spec', ''),
            'unit'        => (string)$request->post('unit', '瓶'),
            'total_qty'   => (int)$request->post('total_qty', 0),
            'images'      => $request->post('images', []),
            'expire_days' => $request->post('expire_days'),
            'remark'      => (string)$request->post('remark', ''),
        ];

        v::intVal()->positive()->setTemplate('请选择会员')->assert($data['member_id']);
        v::stringType()->notEmpty()->setTemplate('请填写酒名')->assert($data['wine_name']);

        return Result::success(WineService::createStorage($data, (int)$request->adminId), '登记成功');
    }

    public function verifyWineTake(Request $request): Response
    {
        $takeNo = trim((string)$request->post('take_no', ''));
        v::stringType()->notEmpty()->setTemplate('缺少取酒码')->assert($takeNo);

        return Result::success(WineService::verifyTake($takeNo, (int)$request->adminId), '核销成功');
    }

    public function verifyExchange(Request $request): Response
    {
        $recordNo = trim((string)$request->post('record_no', ''));
        v::stringType()->notEmpty()->setTemplate('缺少兑换码')->assert($recordNo);

        return Result::success(ExchangeService::verify($recordNo, (int)$request->adminId), '核销成功');
    }

    public function wineStorages(Request $request): Response
    {
        [$page, $pageSize] = $this->pagination($request);
        $memberId = (int)$request->get('member_id', 0);
        $status   = $request->get('status');

        $result = WineService::storages(
            $memberId,
            $status === null || $status === '' ? null : (int)$status,
            $page,
            $pageSize
        );

        return Result::page($result['list'], $result['total'], $page, $pageSize);
    }

    public function exchangeRecords(Request $request): Response
    {
        [$page, $pageSize] = $this->pagination($request);
        $status = $request->get('status');

        $query = ExchangeRecord::query();
        if ($status !== null && $status !== '') {
            $query->where('status', (int)$status);
        }
        if ($memberId = (int)$request->get('member_id', 0)) {
            $query->where('member_id', $memberId);
        }

        $total = (int)$query->count();
        $list  = $query->orderByDesc('id')
            ->forPage($page, $pageSize)
            ->get()
            ->map(static fn(ExchangeRecord $record): array => ExchangeService::format($record))
            ->all();

        return Result::page($list, $total, $page, $pageSize);
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
