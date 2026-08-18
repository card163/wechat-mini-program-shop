<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\model\RechargePackage;

class RechargePackageController extends CrudController
{
    protected function model(): string
    {
        return RechargePackage::class;
    }

    protected function fillable(): array
    {
        return ['title', 'amount', 'gift_amount', 'gift_point', 'gift_expire_days', 'sort', 'status'];
    }

    protected function searchable(): array
    {
        return ['title'];
    }
}
