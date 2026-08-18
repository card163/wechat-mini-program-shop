<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\model\ExchangeGoods;

class ExchangeGoodsController extends CrudController
{
    protected function model(): string
    {
        return ExchangeGoods::class;
    }

    protected function fillable(): array
    {
        return ['type', 'name', 'cover', 'point', 'gift_amount', 'gift_expire_days', 'stock', 'description', 'sort', 'status'];
    }
}
