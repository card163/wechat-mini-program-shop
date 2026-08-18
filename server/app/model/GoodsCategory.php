<?php

declare(strict_types=1);

namespace app\model;

/**
 * 商品分类
 */
class GoodsCategory extends BaseModel
{
    public const int STATUS_ON  = 1;
    public const int STATUS_OFF = 0;

    protected $table = 'nf_goods_category';

    protected $casts = [
        'sort'       => 'integer',
        'status'     => 'integer',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
}
