<?php

declare(strict_types=1);

namespace app\model;

/**
 * 商品
 *
 * @property int $price 售价(分)
 * @property int $stock 库存，-1 表示不限
 * @property int $gift_payable 是否可用赠金支付
 * @property int $gift_amount 使用赠金支付时兑换该商品(单件)需消耗的赠金数量，单位见配置 point.gift_unit
 * @property int $drink_card_payable 是否可用饮品卡支付
 * @property int $drink_card_amount 使用饮品卡支付时兑换该商品(单件)需消耗的数量，单位见配置 point.drink_card_unit
 * @property int $drink_card_gift_amount 购买该商品(单件)赠送饮品卡数量，0表示不赠送
 * @property int $drink_card_gift_expire_days 购买赠送饮品卡有效天数，0表示永久有效
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
        'gift_amount'  => 'integer',
        'drink_card_payable' => 'integer',
        'drink_card_amount'  => 'integer',
        'drink_card_gift_amount'      => 'integer',
        'drink_card_gift_expire_days' => 'integer',
        'sort'         => 'integer',
        'status'       => 'integer',
        'created_at'   => 'datetime:Y-m-d H:i:s',
        'updated_at'   => 'datetime:Y-m-d H:i:s',
    ];
}
