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
        'drink_card_gift_amount'      => 'integer',
        'drink_card_gift_expire_days' => 'integer',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
}
