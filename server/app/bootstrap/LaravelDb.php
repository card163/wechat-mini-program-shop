<?php

declare(strict_types=1);

namespace app\bootstrap;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Pagination\Paginator;
use Throwable;
use Webman\Bootstrap;
use Workerman\Timer;
use Workerman\Worker;

class LaravelDb implements Bootstrap
{
    public static function start(?Worker $worker): void
    {
        $capsule = new Capsule();
        $connections = config('database.connections', []);
        foreach ($connections as $name => $config) {
            $capsule->addConnection($config, $name);
        }
        $capsule->setEventDispatcher(new Dispatcher(new Container()));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $capsule->getDatabaseManager()->setDefaultConnection(config('database.default'));

        // 长连接保活，避免 MySQL wait_timeout 断开
        if ($worker) {
            Timer::add(55, static function () use ($capsule, $connections): void {
                foreach ($connections as $name => $config) {
                    if (($config['driver'] ?? '') !== 'mysql') {
                        continue;
                    }
                    try {
                        $capsule->getConnection($name)->select('select 1');
                    } catch (Throwable) {
                    }
                }
            });
        }

        Paginator::currentPageResolver(static function (string $pageName = 'page'): int {
            $page = (int)(request()?->input($pageName, 1) ?? 1);
            return $page > 0 ? $page : 1;
        });
    }
}
