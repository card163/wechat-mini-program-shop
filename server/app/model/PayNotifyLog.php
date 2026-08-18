<?php

declare(strict_types=1);

namespace app\model;

/**
 * 微信支付回调日志
 */
class PayNotifyLog extends BaseModel
{
    public const int BIZ_ORDER    = 1;
    public const int BIZ_RECHARGE = 2;

    protected $table = 'nf_pay_notify_log';

    protected $casts = [
        'biz_type'   => 'integer',
        'amount'     => 'integer',
        'payload'    => 'array',
        'handled'    => 'integer',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
}
