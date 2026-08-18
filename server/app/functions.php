<?php
/**
 * Here is your custom functions.
 */

if (!function_exists('env')) {
    /**
     * 读取 .env 配置，支持 true/false/null/empty 字面量转换
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        return match (strtolower((string)$value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}

if (!function_exists('register_crud_routes')) {
    /**
     * 注册后台标准资源路由
     *
     * @param class-string $controller
     */
    function register_crud_routes(string $prefix, string $controller): void
    {
        Webman\Route::get($prefix, [$controller, 'index']);
        Webman\Route::get($prefix . '/{id:\d+}', [$controller, 'show']);
        Webman\Route::post($prefix, [$controller, 'store']);
        Webman\Route::put($prefix . '/{id:\d+}', [$controller, 'update']);
        Webman\Route::delete($prefix . '/{id:\d+}', [$controller, 'destroy']);
    }
}
