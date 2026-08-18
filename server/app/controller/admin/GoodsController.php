<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\model\Goods;
use app\support\Result;
use support\Request;
use support\Response;

class GoodsController extends CrudController
{
    protected function model(): string
    {
        return Goods::class;
    }

    protected function fillable(): array
    {
        return [
            'category_id', 'name', 'subtitle', 'cover', 'images', 'price', 'origin_price',
            'unit', 'stock', 'gift_payable', 'sort', 'status', 'description',
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'subtitle'];
    }

    public function index(Request $request): Response
    {
        $categoryId = (int)$request->get('category_id', 0);
        if ($categoryId <= 0) {
            return parent::index($request);
        }

        $page     = max(1, (int)$request->get('page', 1));
        $pageSize = min(100, max(1, (int)$request->get('page_size', 20)));
        $query    = Goods::query()->where('category_id', $categoryId);

        $total = (int)$query->count();
        $list  = $query->orderBy('sort')->orderByDesc('id')->forPage($page, $pageSize)->get()->all();

        return Result::page($list, $total, $page, $pageSize);
    }

    public function status(Request $request, int $id): Response
    {
        $goods = $this->find($id);
        $goods->status = (int)$request->post('status', 0) === 1 ? Goods::STATUS_ON : Goods::STATUS_OFF;
        $goods->save();

        return Result::success($goods, '操作成功');
    }
}
