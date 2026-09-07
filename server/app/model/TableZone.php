<?php

declare(strict_types=1);

namespace app\model;

/**
 * 桌号分区（一号台 / 二号台）
 */
class TableZone extends BaseModel
{
    public const int STATUS_ON  = 1;
    public const int STATUS_OFF = 0;

    protected $table = 'nf_table_zone';

    protected $casts = [
        'sort'       => 'integer',
        'status'     => 'integer',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
}
