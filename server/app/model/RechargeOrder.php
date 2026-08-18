<?php

declare(strict_types=1);

namespace app\model;

/**
 * 充值订单
 */
class RechargeOrder extends BaseModel
{
    public const int PAY_STATUS_UNPAID = 0;
    public const int PAY_STATUS_PAID   = 1;
    public const int PAY_STATUS_CLOSED = 2;

    protected $table = 'nf_recharge_order';

    protected $casts = [
        'member_id'   => 'integer',
        'package_id'  => 'integer',
        'amount'      => 'integer',
        'gift_amount' => 'integer',
        'gift_point'  => 'integer',
        'pay_status'  => 'integer',
        'paid_at'     => 'datetime:Y-m-d H:i:s',
        'created_at'  => 'datetime:Y-m-d H:i:s',
        'updated_at'  => 'datetime:Y-m-d H:i:s',
    ];
}
