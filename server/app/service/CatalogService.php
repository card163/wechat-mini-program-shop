<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\DiningTable;
use app\model\Goods;
use app\model\GoodsCategory;
use app\support\Result;

class CatalogService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function categories(): array
    {
        return GoodsCategory::query()
            ->where('status', GoodsCategory::STATUS_ON)
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'name', 'icon'])
            ->map(static fn(GoodsCategory $item): array => [
                'id'   => (int)$item->id,
                'name' => (string)$item->name,
                'icon' => (string)$item->icon,
            ])
            ->all();
    }

    /**
     * @return array{list: array<int, array<string, mixed>>, total: int}
     */
    public static function goodsList(int $categoryId, string $keyword, int $page, int $pageSize): array
    {
        $query = Goods::query()->where('status', Goods::STATUS_ON);

        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }
        if ($keyword !== '') {
            $query->where('name', 'like', '%' . self::escapeLike($keyword) . '%');
        }

        $total = (int)$query->count();
        $list  = $query->orderBy('sort')
            ->orderByDesc('id')
            ->forPage($page, $pageSize)
            ->get()
            ->map(static fn(Goods $goods): array => self::formatGoods($goods))
            ->all();

        return ['list' => $list, 'total' => $total];
    }

    /**
     * @return array<string, mixed>
     */
    public static function goodsDetail(int $goodsId): array
    {
        $goods = Goods::query()->where('status', Goods::STATUS_ON)->find($goodsId);
        if ($goods === null) {
            throw new BusinessException('商品不存在或已下架', Result::NOT_FOUND);
        }

        return self::formatGoods($goods) + [
            'images'      => (array)($goods->images ?? []),
            'description' => (string)$goods->description,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function tables(): array
    {
        return DiningTable::query()
            ->where('status', DiningTable::STATUS_ON)
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(static fn(DiningTable $table): array => [
                'id'   => (int)$table->id,
                'name' => (string)$table->name,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function formatGoods(Goods $goods): array
    {
        return [
            'id'           => (int)$goods->id,
            'category_id'  => (int)$goods->category_id,
            'name'         => (string)$goods->name,
            'subtitle'     => (string)$goods->subtitle,
            'cover'        => (string)$goods->cover,
            'price'        => (int)$goods->price,
            'origin_price' => (int)$goods->origin_price,
            'unit'         => (string)$goods->unit,
            'stock'        => (int)$goods->stock,
            'sales'        => (int)$goods->sales,
            'gift_payable' => (int)$goods->gift_payable,
        ];
    }

    private static function escapeLike(string $keyword): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $keyword);
    }
}
