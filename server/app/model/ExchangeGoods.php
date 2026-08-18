<?php

declare(strict_types=1);

namespace app\model;

/**
 * 记分牌兑换商品
 */
class ExchangeGoods extends BaseModel
{
    public const int TYPE_GOODS = 1;
    public const int TYPE_GIFT  = 2;

    public const int STATUS_ON  = 1;
    public const int STATUS_OFF = 0;

    public const int STOCK_UNLIMITED = -1;

    protected $table = 'nf_exchange_goods';

    protected $casts = [
        'type'             => 'integer',
        'point'            => 'integer',
        'gift_amount'      => 'integer',
        'gift_expire_days' => 'integer',
        'stock'            => 'integer',
        'exchanged'        => 'integer',
        'sort'             => 'integer',
        'status'           => 'integer',
        'created_at'       => 'datetime:Y-m-d H:i:s',
        'updated_at'       => 'datetime:Y-m-d H:i:s',
    ];
}
