<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\exception\BusinessException;
use app\model\Goods;
use app\model\GoodsCategory;
use app\service\SettingService;
use app\support\Result;
use Illuminate\Database\Capsule\Manager as Db;
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
            'unit', 'code', 'stock', 'gift_payable', 'gift_amount', 'drink_card_payable', 'drink_card_amount',
            'drink_card_gift_amount', 'drink_card_gift_expire_days',
            'sort', 'status', 'description',
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'subtitle'];
    }

    protected function validateInput(array $data, int $id = 0): void
    {
        if (isset($data['gift_payable']) && (int)$data['gift_payable'] === 1 && (int)($data['gift_amount'] ?? 0) <= 0) {
            throw new BusinessException('请填写使用多少' . SettingService::giftDisplayName());
        }
        if (isset($data['drink_card_payable']) && (int)$data['drink_card_payable'] === 1 && (int)($data['drink_card_amount'] ?? 0) <= 0) {
            throw new BusinessException('请填写使用多少' . SettingService::drinkCardDisplayName());
        }
        if (isset($data['code']) && trim((string)$data['code']) !== '') {
            $code   = trim((string)$data['code']);
            $exists = Goods::query()
                ->where('code', $code)
                ->when($id > 0, static fn($query) => $query->where('id', '!=', $id))
                ->exists();
            if ($exists) {
                throw new BusinessException('该外部编码已被使用，请更换');
            }
        }
    }

    public function index(Request $request): Response
    {
        $page       = max(1, (int)$request->get('page', 1));
        $pageSize   = min(100, max(1, (int)$request->get('page_size', 20)));
        $categoryId = (int)$request->get('category_id', 0);
        $status     = $request->get('status');
        $keyword    = trim((string)$request->get('keyword', ''));
        $lowStock   = (int)$request->get('low_stock', 0) === 1;

        $query = Goods::query();
        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int)$status);
        }
        if ($keyword !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $keyword);
            $query->where(function ($builder) use ($escaped): void {
                $builder->where('name', 'like', "%$escaped%")->orWhere('subtitle', 'like', "%$escaped%");
            });
        }
        if ($lowStock) {
            $query->where('stock', '>=', 0)->where('stock', '<', Goods::LOW_STOCK_THRESHOLD);
        }

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

    /**
     * 同分类内与相邻商品交换排序值（前移/后移），排序规则与列表展示顺序(sort升序,id降序)保持一致
     */
    public function move(Request $request, int $id): Response
    {
        $direction = (string)$request->post('direction', '');
        if (!in_array($direction, ['up', 'down'], true)) {
            throw new BusinessException('排序方向参数错误');
        }

        $goods = $this->find($id);
        $siblings = Goods::query()
            ->where('category_id', $goods->category_id)
            ->orderBy('sort')
            ->orderByDesc('id')
            ->get(['id', 'sort']);

        $index = $siblings->search(static fn($item) => (int)$item->id === $id);
        if ($index === false) {
            throw new BusinessException('数据异常，请刷新后重试');
        }

        $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if ($targetIndex < 0 || $targetIndex >= $siblings->count()) {
            throw new BusinessException($direction === 'up' ? '已经是第一个' : '已经是最后一个');
        }

        $target = $siblings->get($targetIndex);
        [$sortA, $sortB] = [$goods->sort, $target->sort];
        $goods->sort = $sortB;
        $goods->save();
        Goods::query()->where('id', $target->id)->update(['sort' => $sortA]);

        return Result::success(null, '操作成功');
    }

    /**
     * 批量排序：按前端传入的最终顺序，把同一分类下这些商品的 sort 依次重写为 1..N
     */
    public function batchSort(Request $request): Response
    {
        $categoryId = (int)$request->post('category_id', 0);
        $ids        = array_values(array_unique(array_map('intval', (array)$request->post('ids', []))));
        if ($categoryId <= 0 || $ids === []) {
            throw new BusinessException('参数错误');
        }

        $count = Goods::query()->where('category_id', $categoryId)->whereIn('id', $ids)->count();
        if ($count !== count($ids)) {
            throw new BusinessException('数据已变化，请刷新后重试');
        }

        Db::connection()->transaction(function () use ($ids): void {
            foreach ($ids as $index => $id) {
                Goods::query()->where('id', $id)->update(['sort' => $index + 1]);
            }
        });

        return Result::success(null, '排序已保存');
    }

    /**
     * 批量分类：把选中的商品统一移动到目标分类
     */
    public function batchCategory(Request $request): Response
    {
        $categoryId = (int)$request->post('category_id', 0);
        $ids        = array_values(array_unique(array_map('intval', (array)$request->post('ids', []))));
        if ($categoryId <= 0 || $ids === []) {
            throw new BusinessException('参数错误');
        }
        if (GoodsCategory::query()->find($categoryId) === null) {
            throw new BusinessException('目标分类不存在');
        }

        Goods::query()->whereIn('id', $ids)->update(['category_id' => $categoryId]);

        return Result::success(null, '移动成功');
    }

    /**
     * 批量改库存：按前端传入的 {id,stock} 列表逐个更新，stock 允许 -1(不限库存)
     */
    public function batchStock(Request $request): Response
    {
        $items = (array)$request->post('items', []);
        if ($items === []) {
            throw new BusinessException('请提供需要修改库存的商品');
        }

        $updates = [];
        foreach ($items as $item) {
            $id    = (int)($item['id'] ?? 0);
            $stock = (int)($item['stock'] ?? 0);
            if ($id <= 0) {
                throw new BusinessException('商品ID不合法');
            }
            if ($stock < Goods::STOCK_UNLIMITED) {
                throw new BusinessException('库存不能小于 -1');
            }
            $updates[$id] = $stock;
        }

        $ids   = array_keys($updates);
        $count = Goods::query()->whereIn('id', $ids)->count();
        if ($count !== count($ids)) {
            throw new BusinessException('数据已变化，请刷新后重试');
        }

        Db::connection()->transaction(function () use ($updates): void {
            foreach ($updates as $id => $stock) {
                Goods::query()->where('id', $id)->update(['stock' => $stock]);
            }
        });

        return Result::success(null, '库存已更新');
    }
}
