<?php

declare(strict_types=1);

namespace app\model;

/**
 * 会员记分牌流水
 */
class MemberPointLog extends BaseModel
{
    public const int BIZ_STORE_IN       = 1;
    public const int BIZ_CONSUME_GAIN   = 2;
    public const int BIZ_EXCHANGE_GIFT  = 3;
    public const int BIZ_EXCHANGE_GOODS = 4;
    public const int BIZ_ADMIN_ADJUST   = 5;
    public const int BIZ_REFUND_ROLLBACK = 6;

    protected $table = 'nf_member_point_log';

    protected $casts = [
        'member_id'    => 'integer',
        'point'        => 'integer',
        'before_point' => 'integer',
        'after_point'  => 'integer',
        'biz_type'     => 'integer',
        'biz_id'       => 'integer',
        'operator_id'  => 'integer',
        'created_at'   => 'datetime:Y-m-d H:i:s',
        'updated_at'   => 'datetime:Y-m-d H:i:s',
    ];
}
