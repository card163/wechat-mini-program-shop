<?php

declare(strict_types=1);

namespace app\process;

use app\service\AccountService;
use app\service\OrderService;
use app\service\WineService;
use support\Log;
use Throwable;
use Workerman\Timer;

/**
 * 后台定时任务，单进程运行避免重复执行
 */
class Task
{
    public function onWorkerStart(): void
    {
        // 关闭超时未支付订单并回滚库存
        Timer::add(60, static function (): void {
            self::run('closeExpiredOrders', static fn(): int => OrderService::closeExpired());
        });

        // 回收到期赠金
        Timer::add(300, static function (): void {
            self::run('expireGiftBatches', static fn(): int => AccountService::expireGiftBatches());
        });

        // 标记过期存酒
        Timer::add(3600, static function (): void {
            self::run('expireWineStorages', static fn(): int => WineService::expireStorages());
        });
    }

    private static function run(string $name, callable $handler): void
    {
        try {
            $affected = (int)$handler();
            if ($affected > 0) {
                Log::info("定时任务 $name 处理 $affected 条");
            }
        } catch (Throwable $e) {
            Log::error("定时任务 $name 执行失败：" . $e->getMessage());
        }
    }
}
