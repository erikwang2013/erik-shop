<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\model\Orders;
use app\model\Products;
use app\model\Users;

/**
 * 起始页平台统计（公开接口）
 * 订单已支付状态集合：1已付款/2已发货/3已收货/4已完成
 */
class StatsController extends \app\controller\BaseApiController
{
    // 计入 GMV 的已支付订单状态（排除待付款/已取消/退款中/已退款/待审核）
    private const PAID_STATUSES = [1, 2, 3, 4];

    public function index(): \support\Response
    {
        $todayStart = date('Y-m-d 00:00:00');

        $totalGmv = (float) Orders::whereIn('status', self::PAID_STATUSES)->sum('total_amount');
        $todayGmv = (float) Orders::where('created_at', '>=', $todayStart)
            ->whereIn('status', self::PAID_STATUSES)->sum('total_amount');

        $recentOrders = Orders::orderByDesc('id')->limit(5)
            ->get(['id', 'order_no', 'total_amount', 'status', 'created_at'])->toArray();

        return ApiResponse::success([
            'app' => 'Shop',
            'version' => '1.4.0',
            'timestamp' => date('c'),
            'total' => [
                'users' => Users::count(),
                'products' => Products::count(),
                'orders' => Orders::count(),
                'gmv' => $totalGmv,
            ],
            'today' => [
                'new_users' => Users::where('created_at', '>=', $todayStart)->count(),
                'new_orders' => Orders::where('created_at', '>=', $todayStart)->count(),
                'gmv' => $todayGmv,
            ],
            'recent_orders' => $recentOrders,
        ]);
    }
}
