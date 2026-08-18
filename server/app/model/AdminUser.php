<?php

declare(strict_types=1);

namespace app\model;

/**
 * 后台管理员 / 店员
 */
class AdminUser extends BaseModel
{
    public const int ROLE_SUPER = 1;
    public const int ROLE_STAFF = 2;

    public const int STATUS_NORMAL   = 1;
    public const int STATUS_DISABLED = 0;

    protected $table = 'nf_admin_user';

    protected $hidden = ['password'];

    protected $casts = [
        'role'          => 'integer',
        'status'        => 'integer',
        'last_login_at' => 'datetime:Y-m-d H:i:s',
        'created_at'    => 'datetime:Y-m-d H:i:s',
        'updated_at'    => 'datetime:Y-m-d H:i:s',
    ];
}
