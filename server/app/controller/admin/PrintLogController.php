<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\model\PrintLog;
use app\support\Result;
use support\Request;
use support\Response;

class PrintLogController
{
    private const array STATUS_TEXT = [
        PrintLog::STATUS_PENDING => '待打印',
        PrintLog::STATUS_SUCCESS => '成功',
        PrintLog::STATUS_FAILED  => '失败',
    ];

    public function index(Request $request): Response
    {
        $page     = max(1, (int)$request->get('page', 1));
        $pageSize = min(100, max(1, (int)$request->get('page_size', 20)));
        $printerId = $request->get('printer_id');
        $orderNo   = trim((string)$request->get('order_no', ''));
        $status    = $request->get('status');

        $query = PrintLog::query()->with('printer');
        if ($printerId !== null && $printerId !== '') {
            $query->where('printer_id', (int)$printerId);
        }
        if ($orderNo !== '') {
            $query->where('order_no', $orderNo);
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int)$status);
        }

        $total = (int)$query->count();
        $list  = $query->orderByDesc('id')
            ->forPage($page, $pageSize)
            ->get()
            ->map(static fn(PrintLog $log): array => [
                'id'           => (int)$log->id,
                'printer_id'   => (int)$log->printer_id,
                'printer_name' => (string)($log->printer->name ?? ''),
                'order_id'     => (int)$log->order_id,
                'order_no'     => (string)$log->order_no,
                'vendor'       => (int)$log->vendor,
                'status'       => (int)$log->status,
                'status_text'  => self::STATUS_TEXT[(int)$log->status] ?? '',
                'third_no'     => (string)$log->third_no,
                'fail_reason'  => (string)$log->fail_reason,
                'created_at'   => (string)$log->created_at,
            ])
            ->all();

        return Result::page($list, $total, $page, $pageSize);
    }
}
