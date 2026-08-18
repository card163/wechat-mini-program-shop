<?php

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 点单订单
 */
class Order extends BaseModel
{
    public const int PAY_TYPE_WECHAT  = 1;
    public const int PAY_TYPE_BALANCE = 2;

    public const int PAY_STATUS_UNPAID   = 0;
    public const int PAY_STATUS_PAID     = 1;
    public const int PAY_STATUS_REFUNDED = 2;

    public const int STATUS_UNPAID    = 0;
    public const int STATUS_PAID      = 1;
    public const int STATUS_FINISHED  = 2;
    public const int STATUS_CANCELLED = 3;

    protected $table = 'nf_order';

    protected $casts = [
        'member_id'    => 'integer',
        'table_id'     => 'integer',
        'total_amount' => 'integer',
        'pay_amount'   => 'integer',
        'pay_type'     => 'integer',
        'pay_balance'  => 'integer',
        'pay_gift'     => 'integer',
        'pay_wechat'   => 'integer',
        'pay_status'   => 'integer',
        'order_status' => 'integer',
        'gain_point'   => 'integer',
        'paid_at'      => 'datetime:Y-m-d H:i:s',
        'finished_at'  => 'datetime:Y-m-d H:i:s',
        'cancelled_at' => 'datetime:Y-m-d H:i:s',
        'created_at'   => 'datetime:Y-m-d H:i:s',
        'updated_at'   => 'datetime:Y-m-d H:i:s',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'id');
    }
}
