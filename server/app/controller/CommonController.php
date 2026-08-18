<?php

declare(strict_types=1);

namespace app\controller;

use app\model\Setting;
use app\support\Result;
use support\Response;
use Throwable;

class CommonController
{
    /**
     * 健康检查，供部署探活使用
     */
    public function health(): Response
    {
        $db = 'ok';
        try {
            Setting::query()->limit(1)->exists();
        } catch (Throwable $e) {
            $db = 'error: ' . $e->getMessage();
        }

        return Result::success([
            'app'  => (string)config('app.name', 'nice-fold'),
            'time' => date('Y-m-d H:i:s'),
            'db'   => $db,
        ]);
    }
}
