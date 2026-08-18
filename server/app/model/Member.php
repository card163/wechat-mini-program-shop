<?php

declare(strict_types=1);

namespace app\model;

/**
 * 会员
 *
 * @property string $openid
 * @property string $nickname
 * @property string $avatar
 * @property string $phone
 * @property int $balance 本金余额(分)
 * @property int $gift_balance 有效赠金余额(分)
 * @property int $point 当前记分牌
 * @property int $total_point 累计记分牌
 * @property int $status
 */
class Member extends BaseModel
{
    public const int STATUS_NORMAL   = 1;
    public const int STATUS_DISABLED = 0;

    protected $table = 'nf_member';

    protected $casts = [
        'balance'        => 'integer',
        'gift_balance'   => 'integer',
        'point'          => 'integer',
        'total_point'    => 'integer',
        'total_recharge' => 'integer',
        'total_consume'  => 'integer',
        'status'         => 'integer',
        'last_login_at'  => 'datetime:Y-m-d H:i:s',
        'created_at'     => 'datetime:Y-m-d H:i:s',
        'updated_at'     => 'datetime:Y-m-d H:i:s',
    ];

    protected $hidden = ['openid', 'unionid'];
}
