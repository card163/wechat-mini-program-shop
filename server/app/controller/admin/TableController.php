<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\exception\BusinessException;
use app\model\DiningTable;
use app\model\TableZone;

class TableController extends CrudController
{
    protected function model(): string
    {
        return DiningTable::class;
    }

    protected function fillable(): array
    {
        return ['zone_id', 'name', 'sort', 'status'];
    }

    protected function validateInput(array $data): void
    {
        $zoneId = (int)($data['zone_id'] ?? 0);
        if ($zoneId <= 0 || TableZone::query()->find($zoneId) === null) {
            throw new BusinessException('请选择所属分区');
        }
    }
}
