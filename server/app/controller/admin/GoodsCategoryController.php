<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\exception\BusinessException;
use app\model\BaseModel;
use app\model\Goods;
use app\model\GoodsCategory;

class GoodsCategoryController extends CrudController
{
    protected function model(): string
    {
        return GoodsCategory::class;
    }

    protected function fillable(): array
    {
        return ['name', 'icon', 'sort', 'status'];
    }

    protected function beforeDestroy(BaseModel $model): void
    {
        if (Goods::query()->where('category_id', (int)$model->id)->exists()) {
            throw new BusinessException('该分类下还有商品，无法删除');
        }
    }
}
