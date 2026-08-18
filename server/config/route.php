<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use app\controller\admin\AdminUserController;
use app\controller\admin\AuthController as AdminAuthController;
use app\controller\admin\BannerController;
use app\controller\admin\ExchangeGoodsController;
use app\controller\admin\GoodsCategoryController;
use app\controller\admin\GoodsController as AdminGoodsController;
use app\controller\admin\MemberController as AdminMemberController;
use app\controller\admin\OrderController as AdminOrderController;
use app\controller\admin\PrinterController;
use app\controller\admin\PrintLogController;
use app\controller\admin\RechargePackageController;
use app\controller\admin\SettingController;
use app\controller\admin\StatController;
use app\controller\admin\TableController;
use app\controller\admin\UploadController;
use app\controller\admin\VerifyController;
use app\controller\api\AuthController as ApiAuthController;
use app\controller\api\ExchangeController;
use app\controller\api\GoodsController;
use app\controller\api\HomeController;
use app\controller\api\MemberController;
use app\controller\api\NotifyController;
use app\controller\api\OrderController;
use app\controller\api\RechargeController;
use app\controller\api\WineController;
use app\controller\CommonController;
use app\middleware\AdminAuth;
use app\middleware\AdminRole;
use app\middleware\MemberAuth;
use app\support\Result;
use Webman\Route;

// 后台标准资源路由，定义在本文件内避免依赖外部函数的加载时机
$crud = static function (string $prefix, string $controller): void {
    Route::get($prefix, [$controller, 'index']);
    Route::get($prefix . '/{id:\d+}', [$controller, 'show']);
    Route::post($prefix, [$controller, 'store']);
    Route::put($prefix . '/{id:\d+}', [$controller, 'update']);
    Route::delete($prefix . '/{id:\d+}', [$controller, 'destroy']);
};

// 健康检查（部署探活用，必须无鉴权且始终 200）
Route::get('/health', [CommonController::class, 'health']);

// ------------------------- 小程序 /api -------------------------
// 公开接口
Route::group('/api', function (): void {
    Route::post('/auth/login', [ApiAuthController::class, 'login']);
    Route::get('/home', [HomeController::class, 'index']);
    Route::get('/shop/info', [HomeController::class, 'shopInfo']);
    Route::get('/ranking', [HomeController::class, 'ranking']);
    Route::get('/goods/categories', [GoodsController::class, 'categories']);
    Route::get('/goods', [GoodsController::class, 'index']);
    Route::get('/goods/{id:\d+}', [GoodsController::class, 'detail']);
    Route::get('/tables', [GoodsController::class, 'tables']);
    Route::get('/recharge/packages', [RechargeController::class, 'packages']);
    Route::get('/exchange/goods', [ExchangeController::class, 'goods']);
    // 微信支付回调，由微信服务器调用，内部自行验签
    Route::post('/notify/wechat/pay', [NotifyController::class, 'wechatPay']);
});

// 需要会员登录
Route::group('/api', function (): void {
    Route::post('/auth/profile', [ApiAuthController::class, 'profile']);
    Route::post('/auth/phone', [ApiAuthController::class, 'phone']);
    Route::post('/auth/logout', [ApiAuthController::class, 'logout']);

    Route::get('/member/info', [MemberController::class, 'info']);
    Route::get('/member/balance-logs', [MemberController::class, 'balanceLogs']);
    Route::get('/member/gift-logs', [MemberController::class, 'giftLogs']);
    Route::get('/member/point-logs', [MemberController::class, 'pointLogs']);
    Route::get('/member/gift-batches', [MemberController::class, 'giftBatches']);

    Route::post('/order/preview', [OrderController::class, 'preview']);
    Route::post('/orders', [OrderController::class, 'create']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id:\d+}', [OrderController::class, 'detail']);
    Route::post('/orders/{id:\d+}/pay', [OrderController::class, 'pay']);
    Route::post('/orders/{id:\d+}/cancel', [OrderController::class, 'cancel']);

    Route::post('/recharge/orders', [RechargeController::class, 'create']);
    Route::get('/recharge/orders', [RechargeController::class, 'index']);
    Route::get('/recharge/orders/{id:\d+}', [RechargeController::class, 'detail']);

    Route::post('/exchange', [ExchangeController::class, 'exchange']);
    Route::post('/exchange/points', [ExchangeController::class, 'exchangeByPoint']);
    Route::get('/exchange/records', [ExchangeController::class, 'records']);
    Route::get('/exchange/records/{id:\d+}/code', [ExchangeController::class, 'code']);

    Route::get('/wine/storages', [WineController::class, 'storages']);
    Route::get('/wine/store-code', [WineController::class, 'storeCode']);
    Route::post('/wine/storages/{id:\d+}/take', [WineController::class, 'take']);
    Route::get('/wine/takes', [WineController::class, 'takes']);
    Route::post('/wine/takes/{id:\d+}/cancel', [WineController::class, 'cancelTake']);
})->middleware([MemberAuth::class]);

// ==================== 管理后台 /admin ====================
Route::post('/admin/auth/login', [AdminAuthController::class, 'login']);

// 登录即可访问（含店员）
Route::group('/admin', function (): void {
    Route::get('/auth/profile', [AdminAuthController::class, 'profile']);
    Route::post('/auth/change-password', [AdminAuthController::class, 'changePassword']);

    // 店员核销
    Route::post('/wine/scan', [VerifyController::class, 'scanWineScene']);
    Route::post('/wine/storages', [VerifyController::class, 'storeWine']);
    Route::get('/wine/storages', [VerifyController::class, 'wineStorages']);
    Route::post('/wine/takes/verify', [VerifyController::class, 'verifyWineTake']);
    Route::post('/exchange/verify', [VerifyController::class, 'verifyExchange']);
    Route::get('/exchange-records', [VerifyController::class, 'exchangeRecords']);

    // 订单处理
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{id:\d+}', [AdminOrderController::class, 'show']);
    Route::post('/orders/{id:\d+}/finish', [AdminOrderController::class, 'finish']);
    Route::post('/orders/{id:\d+}/print', [AdminOrderController::class, 'print']);

    Route::post('/upload/image', [UploadController::class, 'image']);
})->middleware([AdminAuth::class]);

// 仅超级管理员
Route::group('/admin', function () use ($crud): void {
    Route::post('/orders/{id:\d+}/refund', [AdminOrderController::class, 'refund']);

    Route::get('/members', [AdminMemberController::class, 'index']);
    Route::get('/members/{id:\d+}', [AdminMemberController::class, 'show']);
    Route::post('/members/{id:\d+}/status', [AdminMemberController::class, 'status']);
    Route::post('/members/{id:\d+}/phone', [AdminMemberController::class, 'updatePhone']);
    Route::post('/members/{id:\d+}/balance/adjust', [AdminMemberController::class, 'adjustBalance']);
    Route::post('/members/{id:\d+}/gift/grant', [AdminMemberController::class, 'grantGift']);
    Route::post('/members/{id:\d+}/point/adjust', [AdminMemberController::class, 'adjustPoint']);
    Route::get('/members/{id:\d+}/balance-logs', [AdminMemberController::class, 'balanceLogs']);
    Route::get('/members/{id:\d+}/point-logs', [AdminMemberController::class, 'pointLogs']);

    $crud('/goods-categories', GoodsCategoryController::class);
    $crud('/goods', AdminGoodsController::class);
    Route::post('/goods/{id:\d+}/status', [AdminGoodsController::class, 'status']);
    $crud('/tables', TableController::class);
    $crud('/recharge-packages', RechargePackageController::class);
    $crud('/exchange-goods', ExchangeGoodsController::class);
    $crud('/banners', BannerController::class);

    Route::get('/settings/{group}', [SettingController::class, 'show']);
    Route::put('/settings/{group}', [SettingController::class, 'save']);

    Route::get('/admin-users', [AdminUserController::class, 'index']);
    Route::post('/admin-users', [AdminUserController::class, 'store']);
    Route::put('/admin-users/{id:\d+}', [AdminUserController::class, 'update']);
    Route::delete('/admin-users/{id:\d+}', [AdminUserController::class, 'destroy']);

    $crud('/printers', PrinterController::class);
    Route::post('/printers/{id:\d+}/test-print', [PrinterController::class, 'testPrint']);
    Route::get('/print-logs', [PrintLogController::class, 'index']);

    Route::get('/stat/overview', [StatController::class, 'overview']);
    Route::get('/stat/trend', [StatController::class, 'trend']);
})->middleware([AdminAuth::class, AdminRole::class]);

// 关闭控制器默认路由，所有接口必须显式注册
Route::disableDefaultRoute();

Route::fallback(static fn(): support\Response => Result::error('接口不存在', Result::NOT_FOUND));

