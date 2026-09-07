<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\exception\BusinessException;
use app\model\BaseModel;
use app\model\DiningTable;
use app\model\TableZone;

class TableZoneController extends CrudController
{
    protected function model(): string
    {
        return TableZone::class;
    }

    protected function fillable(): array
    {
        return ['name', 'sort', 'status'];
    }

    protected function beforeDestroy(BaseModel $model): void
    {
        $hasTable = DiningTable::query()->where('zone_id', $model->id)->exists();
        if ($hasTable) {
            throw new BusinessException('该分区下还有桌号，请先删除或移动桌号');
        }
    }
}
