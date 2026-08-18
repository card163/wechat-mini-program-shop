<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\exception\BusinessException;
use app\model\Printer;
use app\service\PrinterService;
use app\support\Result;
use Respect\Validation\Validator as v;
use support\Request;
use support\Response;

class PrinterController
{
    public function index(Request $request): Response
    {
        $page     = max(1, (int)$request->get('page', 1));
        $pageSize = min(100, max(1, (int)$request->get('page_size', 20)));

        $query = Printer::query();
        $total = (int)$query->count();
        $list  = $query->orderBy('sort')->orderByDesc('id')
            ->forPage($page, $pageSize)
            ->get()
            ->map(static fn(Printer $printer): array => self::format($printer))
            ->all();

        return Result::page($list, $total, $page, $pageSize);
    }

    public function show(Request $request, int $id): Response
    {
        return Result::success(self::format($this->find($id)));
    }

    public function store(Request $request): Response
    {
        $printer = new Printer();
        $this->fillFrom($request, $printer, true);
        $printer->save();

        return Result::success(self::format($printer), '创建成功');
    }

    public function update(Request $request, int $id): Response
    {
        $printer = $this->find($id);
        $this->fillFrom($request, $printer, false);
        $printer->save();

        return Result::success(self::format($printer), '保存成功');
    }

    public function destroy(Request $request, int $id): Response
    {
        $this->find($id)->delete();

        return Result::success(null, '删除成功');
    }

    public function testPrint(Request $request, int $id): Response
    {
        return Result::success(PrinterService::testPrint($this->find($id)));
    }

    private function fillFrom(Request $request, Printer $printer, bool $isCreate): void
    {
        $name      = trim((string)$request->post('name', ''));
        $vendor    = (int)$request->post('vendor', 0);
        $sn        = trim((string)$request->post('sn', ''));
        $account   = trim((string)$request->post('account', ''));
        $secretKey = (string)$request->post('secret_key', '');

        v::stringType()->length(1, 50)->setTemplate('请输入打印机名称')->assert($name);
        v::in(Printer::vendors())->setTemplate('厂商类型不正确')->assert($vendor);
        v::stringType()->length(1, 64)->setTemplate('请输入打印机终端编号(SN)')->assert($sn);
        v::stringType()->length(1, 64)->setTemplate('请输入开放平台账号')->assert($account);
        if ($isCreate) {
            v::stringType()->length(1, 128)->setTemplate('请输入密钥')->assert($secretKey);
        }

        $printer->name    = $name;
        $printer->vendor  = $vendor;
        $printer->sn      = $sn;
        $printer->account = $account;
        if ($secretKey !== '') {
            $printer->secret_key = $secretKey;
        }
        $printer->copies      = max(1, (int)$request->post('copies', 1));
        $printer->voice_times = max(0, (int)$request->post('voice_times', 0));
        $printer->status      = (int)$request->post('status', Printer::STATUS_ON) === Printer::STATUS_OFF
            ? Printer::STATUS_OFF
            : Printer::STATUS_ON;
        $printer->sort   = (int)$request->post('sort', 0);
        $printer->remark = mb_substr((string)$request->post('remark', ''), 0, 200);
    }

    private function find(int $id): Printer
    {
        $printer = Printer::query()->find($id);
        if ($printer === null) {
            throw new BusinessException('打印机不存在', Result::NOT_FOUND);
        }

        return $printer;
    }

    /**
     * @return array<string, mixed>
     */
    private static function format(Printer $printer): array
    {
        return [
            'id'          => (int)$printer->id,
            'name'        => (string)$printer->name,
            'vendor'      => (int)$printer->vendor,
            'sn'          => (string)$printer->sn,
            'account'     => (string)$printer->account,
            'secret_key'  => self::maskSecret((string)$printer->secret_key),
            'copies'      => (int)$printer->copies,
            'voice_times' => (int)$printer->voice_times,
            'status'      => (int)$printer->status,
            'sort'        => (int)$printer->sort,
            'remark'      => (string)$printer->remark,
            'created_at'  => (string)$printer->created_at,
        ];
    }

    private static function maskSecret(string $secret): string
    {
        return $secret === '' ? '' : '****' . mb_substr($secret, -4);
    }
}
