<?php

declare(strict_types=1);

namespace app\model;

/**
 * 会员余额流水（本金 / 赠金）
 */
class MemberBalanceLog extends BaseModel
{
    public const int ACCOUNT_PRINCIPAL = 1;
    public const int ACCOUNT_GIFT      = 2;

    public const int BIZ_RECHARGE      = 1;
    public const int BIZ_RECHARGE_GIFT = 2;
    public const int BIZ_CONSUME       = 3;
    public const int BIZ_REFUND        = 4;
    public const int BIZ_POINT_EXCHANGE = 5;
    public const int BIZ_GIFT_EXPIRED  = 6;
    public const int BIZ_ADMIN_ADJUST  = 7;

    protected $table = 'nf_member_balance_log';

    protected $casts = [
        'member_id'      => 'integer',
        'account_type'   => 'integer',
        'gift_batch_id'  => 'integer',
        'amount'         => 'integer',
        'before_balance' => 'integer',
        'after_balance'  => 'integer',
        'biz_type'       => 'integer',
        'biz_id'         => 'integer',
        'operator_id'    => 'integer',
        'created_at'     => 'datetime:Y-m-d H:i:s',
        'updated_at'     => 'datetime:Y-m-d H:i:s',
    ];
}
