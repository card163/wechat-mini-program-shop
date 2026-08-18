<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\exception\BusinessException;
use app\model\BaseModel;
use app\support\Result;
use support\Request;
use support\Response;

/**
 * 后台同构资源的通用增删改查
 */
abstract class CrudController
{
    /** @return class-string<BaseModel> */
    abstract protected function model(): string;

    /** 允许写入的字段 @return array<int, string> */
    abstract protected function fillable(): array;

    /** 支持模糊搜索的字段 @return array<int, string> */
    protected function searchable(): array
    {
        return ['name'];
    }

    /** @return array<string, string> */
    protected function orderBy(): array
    {
        return ['sort' => 'asc', 'id' => 'desc'];
    }

    /**
     * 删除前的业务校验，子类按需覆写
     */
    protected function beforeDestroy(BaseModel $model): void
    {
    }

    public function index(Request $request): Response
    {
        $page     = max(1, (int)$request->get('page', 1));
        $pageSize = min(100, max(1, (int)$request->get('page_size', 20)));
        $keyword  = trim((string)$request->get('keyword', ''));
        $status   = $request->get('status');

        $model = $this->model();
        $query = $model::query();

        if ($keyword !== '' && $this->searchable() !== []) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $keyword);
            $query->where(function ($builder) use ($escaped): void {
                foreach ($this->searchable() as $index => $field) {
                    $index === 0
                        ? $builder->where($field, 'like', "%$escaped%")
                        : $builder->orWhere($field, 'like', "%$escaped%");
                }
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int)$status);
        }

        $total = (int)$query->count();
        foreach ($this->orderBy() as $field => $direction) {
            $query->orderBy($field, $direction);
        }
        $list = $query->forPage($page, $pageSize)->get()->all();

        return Result::page($list, $total, $page, $pageSize);
    }

    public function show(Request $request, int $id): Response
    {
        return Result::success($this->find($id));
    }

    public function store(Request $request): Response
    {
        $model = $this->model();
        $item  = new $model();
        $item->fill($this->input($request));
        $item->save();

        return Result::success($item, '创建成功');
    }

    public function update(Request $request, int $id): Response
    {
        $item = $this->find($id);
        $item->fill($this->input($request));
        $item->save();

        return Result::success($item, '保存成功');
    }

    public function destroy(Request $request, int $id): Response
    {
        $item = $this->find($id);
        $this->beforeDestroy($item);
        $item->delete();

        return Result::success(null, '删除成功');
    }

    protected function find(int $id): BaseModel
    {
        $model = $this->model();
        $item  = $model::query()->find($id);
        if ($item === null) {
            throw new BusinessException('记录不存在', Result::NOT_FOUND);
        }

        return $item;
    }

    /**
     * 只接受白名单字段，避免前端越权写入
     *
     * @return array<string, mixed>
     */
    protected function input(Request $request): array
    {
        $data = [];
        foreach ($this->fillable() as $field) {
            $value = $request->post($field);
            if ($value !== null) {
                $data[$field] = $value;
            }
        }

        if ($data === []) {
            throw new BusinessException('没有需要保存的内容');
        }

        return $data;
    }
}
