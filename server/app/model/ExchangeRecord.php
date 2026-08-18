<?php

declare(strict_types=1);

namespace app\model;

/**
 * 记分牌兑换记录
 */
class ExchangeRecord extends BaseModel
{
    public const int STATUS_PENDING   = 0;
    public const int STATUS_VERIFIED  = 1;
    public const int STATUS_CANCELLED = 2;

    protected $table = 'nf_exchange_record';

    protected $casts = [
        'member_id'       => 'integer',
        'goods_id'        => 'integer',
        'type'            => 'integer',
        'point'           => 'integer',
        'gift_amount'     => 'integer',
        'status'          => 'integer',
        'verify_admin_id' => 'integer',
        'verified_at'     => 'datetime:Y-m-d H:i:s',
        'created_at'      => 'datetime:Y-m-d H:i:s',
        'updated_at'      => 'datetime:Y-m-d H:i:s',
    ];
}
