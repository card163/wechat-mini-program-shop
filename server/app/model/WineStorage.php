<?php

declare(strict_types=1);

namespace app\model;

/**
 * 会员存酒
 */
class WineStorage extends BaseModel
{
    public const int STATUS_STORING = 1;
    public const int STATUS_TAKEN   = 2;
    public const int STATUS_EXPIRED = 3;

    protected $table = 'nf_wine_storage';

    protected $casts = [
        'member_id'       => 'integer',
        'total_qty'       => 'integer',
        'remain_qty'      => 'integer',
        'images'          => 'array',
        'status'          => 'integer',
        'store_admin_id'  => 'integer',
        'stored_at'       => 'datetime:Y-m-d H:i:s',
        'expired_at'      => 'datetime:Y-m-d H:i:s',
        'created_at'      => 'datetime:Y-m-d H:i:s',
        'updated_at'      => 'datetime:Y-m-d H:i:s',
    ];
}
