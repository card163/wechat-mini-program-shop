<?php

declare(strict_types=1);

namespace app\model;

/**
 * 订单明细
 */
class OrderItem extends BaseModel
{
    protected $table = 'nf_order_item';

    protected $casts = [
        'order_id'   => 'integer',
        'goods_id'   => 'integer',
        'price'      => 'integer',
        'quantity'   => 'integer',
        'subtotal'   => 'integer',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
}
