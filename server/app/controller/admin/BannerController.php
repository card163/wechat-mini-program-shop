<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\model\Banner;

class BannerController extends CrudController
{
    protected function model(): string
    {
        return Banner::class;
    }

    protected function fillable(): array
    {
        return ['title', 'image', 'link', 'sort', 'status'];
    }

    protected function searchable(): array
    {
        return ['title'];
    }
}
