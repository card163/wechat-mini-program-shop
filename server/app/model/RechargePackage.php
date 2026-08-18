<?php

declare(strict_types=1);

namespace app\model;

/**
 * 充值套餐
 */
class RechargePackage extends BaseModel
{
    public const int STATUS_ON  = 1;
    public const int STATUS_OFF = 0;

    protected $table = 'nf_recharge_package';

    protected $casts = [
        'amount'           => 'integer',
        'gift_amount'      => 'integer',
        'gift_point'       => 'integer',
        'gift_expire_days' => 'integer',
        'sort'             => 'integer',
        'status'           => 'integer',
        'created_at'       => 'datetime:Y-m-d H:i:s',
        'updated_at'       => 'datetime:Y-m-d H:i:s',
    ];
}
