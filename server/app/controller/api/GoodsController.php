<?php

declare(strict_types=1);

namespace app\controller\api;

use app\service\CatalogService;
use app\support\Result;
use support\Request;
use support\Response;

class GoodsController
{
    public function categories(): Response
    {
        return Result::success(CatalogService::categories());
    }

    public function index(Request $request): Response
    {
        $categoryId = (int)$request->get('category_id', 0);
        $keyword    = trim((string)$request->get('keyword', ''));
        $page       = max(1, (int)$request->get('page', 1));
        $pageSize   = min(100, max(1, (int)$request->get('page_size', 20)));

        $result = CatalogService::goodsList($categoryId, $keyword, $page, $pageSize);

        return Result::page($result['list'], $result['total'], $page, $pageSize);
    }

    public function detail(Request $request, int $id): Response
    {
        return Result::success(CatalogService::goodsDetail($id));
    }

    public function tables(): Response
    {
        return Result::success(CatalogService::tables());
    }
}
