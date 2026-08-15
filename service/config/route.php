<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * API 路由配置
 * 所有路由不带版本号，版本通过 API-Version header 传递
 * VersionRoute 中间件自动映射到对应控制器命名空间
 */

use Webman\Route;

// ===== 公开路由（无需认证） =====
Route::group('/api', function () {

    // 认证
    Route::post('/auth/register', [app\controller\v1\AuthController::class, 'register'])
        ->middleware([app\middleware\PosterVerify::class]);
    Route::post('/auth/login', [app\controller\v1\AuthController::class, 'login']);
    Route::post('/auth/refresh', [app\controller\v1\AuthController::class, 'refresh']);
    Route::post('/auth/social', [app\controller\v1\SocialAuthController::class, 'login']);

    // 人机验证（PosterVerify 签发端：注册/下单/支付前先获取 token）
    Route::get('/poster/challenge', [app\controller\v1\PosterController::class, 'challenge']);
    Route::post('/poster/verify', [app\controller\v1\PosterController::class, 'verify']);

    // 商品与分类
    Route::get('/products', [app\controller\v1\ProductController::class, 'index']);
    Route::get('/products/{id:\w+}', [app\controller\v1\ProductController::class, 'show']);
    Route::get('/categories', [app\controller\v1\CategoryController::class, 'index']);
    Route::get('/categories/tree', [app\controller\v1\CategoryController::class, 'tree']);

    // 内容
    Route::get('/banners', [app\controller\v1\BannerController::class, 'index']);
    Route::get('/countries', [app\controller\v1\CountryController::class, 'index']);
    Route::get('/settings', [app\controller\v1\SettingsController::class, 'public']);
    Route::get('/faq', [app\controller\v1\FaqController::class, 'index']);
    Route::get('/cms/{slug}', [app\controller\v1\CmsController::class, 'show']);

    // 搜索
    Route::get('/search', [app\controller\v1\SearchController::class, 'index']);

    // 评价
    Route::get('/reviews/{productId:\w+}', [app\controller\v1\ReviewController::class, 'index']);

    // 营销
    Route::get('/flash-sales', [app\controller\v1\FlashSaleController::class, 'index']);
    Route::get('/group-buys', [app\controller\v1\GroupBuyController::class, 'index']);

    // 公共服务
    Route::get('/size-charts', [app\controller\v1\SizeChartController::class, 'index']);
    Route::get('/tariff/estimate', [app\controller\v1\TariffController::class, 'estimate']);
    Route::get('/shipping/calculate', [app\controller\v1\ShippingController::class, 'calculate']);
    Route::get('/payment/methods', [app\controller\v1\PaymentController::class, 'methods']);
    Route::get('/geoip/detect', [app\controller\v1\GeoIpController::class, 'detect']);
    Route::get('/compliance/check', [app\controller\v1\ComplianceController::class, 'check']);

});

// ===== 认证路由（需要JWT） =====
Route::group('/api', function () {

    // 用户个人
    Route::get('/user/profile', [app\controller\v1\UserController::class, 'profile']);
    Route::put('/user/profile', [app\controller\v1\UserController::class, 'updateProfile']);
    Route::get('/user/addresses', [app\controller\v1\UserController::class, 'addresses']);
    Route::post('/user/addresses', [app\controller\v1\UserController::class, 'createAddress']);
    Route::put('/user/addresses/{id:\w+}', [app\controller\v1\UserController::class, 'updateAddress']);
    Route::delete('/user/addresses/{id:\w+}', [app\controller\v1\UserController::class, 'deleteAddress']);
    Route::put('/user/locale', [app\controller\v1\UserController::class, 'updateLocale']);

    // 收藏与提醒
    Route::get('/wishlist', [app\controller\v1\WishlistController::class, 'index']);
    Route::post('/wishlist', [app\controller\v1\WishlistController::class, 'store']);
    Route::delete('/wishlist/{id:\w+}', [app\controller\v1\WishlistController::class, 'destroy']);
    Route::get('/price-alerts', [app\controller\v1\PriceAlertController::class, 'index']);
    Route::post('/price-alerts', [app\controller\v1\PriceAlertController::class, 'store']);

    // 购物车
    Route::get('/cart', [app\controller\v1\CartController::class, 'index']);
    Route::post('/cart', [app\controller\v1\CartController::class, 'store']);
    Route::put('/cart/{id:\w+}', [app\controller\v1\CartController::class, 'update']);
    Route::delete('/cart/{id:\w+}', [app\controller\v1\CartController::class, 'destroy']);

    // 订单
    Route::get('/orders', [app\controller\v1\OrderController::class, 'index']);
    Route::get('/orders/{id:\w+}', [app\controller\v1\OrderController::class, 'show']);
    Route::post('/orders', [app\controller\v1\OrderController::class, 'store'])
        ->middleware([app\middleware\PosterVerify::class]);
    Route::post('/orders/{id:\w+}/cancel', [app\controller\v1\OrderController::class, 'cancel']);
    Route::get('/orders/{id:\w+}/documents/invoice', [app\controller\v1\DocumentController::class, 'invoice']);
    Route::get('/orders/{id:\w+}/documents/packing-list', [app\controller\v1\DocumentController::class, 'packingList']);

    // 支付
    Route::post('/payment/create', [app\controller\v1\PaymentController::class, 'create'])
        ->middleware([app\middleware\PosterVerify::class]);
    Route::get('/payment/status/{id:\w+}', [app\controller\v1\PaymentController::class, 'status']);

    // 退货
    Route::get('/returns', [app\controller\v1\ReturnController::class, 'index']);
    Route::post('/returns', [app\controller\v1\ReturnController::class, 'create']);
    Route::get('/returns/{id:\w+}/label', [app\controller\v1\ReturnController::class, 'label']);

    // 评价
    Route::post('/reviews', [app\controller\v1\ReviewController::class, 'store']);

    // 优惠券
    Route::get('/coupons', [app\controller\v1\CouponController::class, 'available']);
    Route::post('/coupons/{id:\w+}/claim', [app\controller\v1\CouponController::class, 'claim']);

    // 通知
    Route::get('/notifications', [app\controller\v1\NotificationController::class, 'index']);
    Route::put('/notifications/{id:\w+}/read', [app\controller\v1\NotificationController::class, 'read']);

    // 对比
    Route::get('/comparisons', [app\controller\v1\ComparisonController::class, 'index']);
    Route::post('/comparisons', [app\controller\v1\ComparisonController::class, 'store']);
    Route::delete('/comparisons/{id:\w+}', [app\controller\v1\ComparisonController::class, 'destroy']);

    // 推荐
    Route::get('/recommendations', [app\controller\v1\RecommendationController::class, 'index']);

    // 分销
    Route::get('/affiliate/links', [app\controller\v1\AffiliateController::class, 'links']);
    Route::get('/affiliate/commissions', [app\controller\v1\AffiliateController::class, 'commissions']);

    // 隐私
    Route::get('/privacy/request', [app\controller\v1\PrivacyController::class, 'index']);
    Route::post('/privacy/request', [app\controller\v1\PrivacyController::class, 'create']);

    // 会员
    Route::get('/membership', [app\controller\v1\MembershipController::class, 'index']);
    Route::get('/points', [app\controller\v1\MembershipController::class, 'points']);

    // 礼品卡
    Route::get('/gift-cards/balance', [app\controller\v1\GiftCardController::class, 'balance']);
    Route::post('/gift-cards/redeem', [app\controller\v1\GiftCardController::class, 'redeem']);

    // 导出
    Route::get('/export/orders', [app\controller\v1\ExportController::class, 'orders']);

    // B2B
    Route::get('/b2b/quotes', [app\controller\v1\B2bController::class, 'index']);
    Route::post('/b2b/quotes', [app\controller\v1\B2bController::class, 'store']);

})->middleware([app\middleware\JwtAuth::class]);

// ===== 敏感操作（PosterVerify 直接挂在路由上，避免与JWT组冲突） =====
// POST /api/auth/register — 公开但需要人机验证（已在公开组，此处追加中间件）
// POST /api/orders — 在认证组中已定义，需追加PosterVerify
// POST /api/payment/create — 同上

// ===== 支付Webhook（无需JWT，需验签） =====
Route::post('/webhook/payment/{gateway:\w+}', [app\controller\v1\PaymentController::class, 'webhook']);

// ===== 管理后台内部接口（需 X-Admin-Key 共享密钥） =====
Route::post('/api/admin/refunds/{id:\w+}/execute', [app\controller\v1\AdminOpsController::class, 'executeRefund'])
    ->middleware([app\middleware\AdminKeyMiddleware::class]);

// ===== 健康检查（无需JWT，供探活/负载均衡） =====
Route::get('/health', [app\controller\v1\HealthController::class, 'index']);

// ===== 关闭默认路由 =====
Route::disableDefaultRoute();
