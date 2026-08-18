<?php

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 打印任务日志
 */
class PrintLog extends BaseModel
{
    public const int STATUS_PENDING = 0;
    public const int STATUS_SUCCESS = 1;
    public const int STATUS_FAILED  = 2;

    protected $table = 'nf_print_log';

    protected $casts = [
        'printer_id' => 'integer',
        'order_id'   => 'integer',
        'vendor'     => 'integer',
        'status'     => 'integer',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class, 'printer_id', 'id');
    }
}
