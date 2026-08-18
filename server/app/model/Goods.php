<?php

declare(strict_types=1);

namespace app\model;

/**
 * 商品
 *
 * @property int $price 售价(分)
 * @property int $stock 库存，-1 表示不限
 * @property int $gift_payable 是否可用赠金支付
 */
class Goods extends BaseModel
{
    public const int STATUS_ON  = 1;
    public const int STATUS_OFF = 0;

    public const int STOCK_UNLIMITED = -1;

    protected $table = 'nf_goods';

    protected $casts = [
        'category_id'  => 'integer',
        'images'       => 'array',
        'price'        => 'integer',
        'origin_price' => 'integer',
        'stock'        => 'integer',
        'sales'        => 'integer',
        'gift_payable' => 'integer',
        'sort'         => 'integer',
        'status'       => 'integer',
        'created_at'   => 'datetime:Y-m-d H:i:s',
        'updated_at'   => 'datetime:Y-m-d H:i:s',
    ];
}
