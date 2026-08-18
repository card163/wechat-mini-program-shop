<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\model\DiningTable;

class TableController extends CrudController
{
    protected function model(): string
    {
        return DiningTable::class;
    }

    protected function fillable(): array
    {
        return ['name', 'sort', 'status'];
    }
}
