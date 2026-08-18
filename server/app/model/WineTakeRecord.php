<?php

declare(strict_types=1);

namespace app\model;

/**
 * 取酒记录（取酒码由店员核销）
 */
class WineTakeRecord extends BaseModel
{
    public const int STATUS_PENDING  = 0;
    public const int STATUS_VERIFIED = 1;
    public const int STATUS_INVALID  = 2;

    protected $table = 'nf_wine_take_record';

    protected $casts = [
        'storage_id'       => 'integer',
        'member_id'        => 'integer',
        'quantity'         => 'integer',
        'status'           => 'integer',
        'verify_admin_id'  => 'integer',
        'verified_at'      => 'datetime:Y-m-d H:i:s',
        'code_expired_at'  => 'datetime:Y-m-d H:i:s',
        'created_at'       => 'datetime:Y-m-d H:i:s',
        'updated_at'       => 'datetime:Y-m-d H:i:s',
    ];
}
