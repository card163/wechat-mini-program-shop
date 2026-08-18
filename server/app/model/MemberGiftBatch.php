<?php

declare(strict_types=1);

namespace app\model;

/**
 * 会员赠金批次，按 expired_at 由近及远消耗
 *
 * @property int $member_id
 * @property int $amount
 * @property int $used_amount
 * @property int $remain_amount
 * @property int $source_type
 * @property int $status
 */
class MemberGiftBatch extends BaseModel
{
    public const int SOURCE_RECHARGE = 1;
    public const int SOURCE_EXCHANGE = 2;
    public const int SOURCE_ADMIN    = 3;
    public const int SOURCE_REFUND   = 4;

    public const int STATUS_VALID   = 1;
    public const int STATUS_USED_UP = 2;
    public const int STATUS_EXPIRED = 3;

    protected $table = 'nf_member_gift_batch';

    protected $casts = [
        'member_id'     => 'integer',
        'amount'        => 'integer',
        'used_amount'   => 'integer',
        'remain_amount' => 'integer',
        'source_type'   => 'integer',
        'source_id'     => 'integer',
        'status'        => 'integer',
        'effective_at'  => 'datetime:Y-m-d H:i:s',
        'expired_at'    => 'datetime:Y-m-d H:i:s',
        'created_at'    => 'datetime:Y-m-d H:i:s',
        'updated_at'    => 'datetime:Y-m-d H:i:s',
    ];
}
