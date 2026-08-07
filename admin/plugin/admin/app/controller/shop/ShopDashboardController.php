<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Base;
use support\Request;

/**
 * @Apidoc\Group("dashboard")
 * @Apidoc\Sort(25)
 */
class ShopDashboardController extends Base
{
    /**
 * @Apidoc\Title("数据面板")
 * @Apidoc\Desc("ECharts可视化仪表盘")
 * @Apidoc\Method("GET")
 * @Apidoc\Url("/app/admin/shop/ShopDashboard/index")
 * @Apidoc\Author("erik")
 */
    public function index(Request $request)
    {
        return view('shop/dashboard/index', [
            'title' => '跨境数据面板',
        ]);
    }

    /**
 * @Apidoc\Title("图表数据")
 * @Apidoc\Desc("近N天销售趋势")
 * @Apidoc\Method("GET")
 * @Apidoc\Url("/app/admin/shop/ShopDashboard/chartData")
 * @Apidoc\Author("erik")
 */
    public function chartData(Request $request)
    {
        $days = max(1, min((int) $request->input('days', 7), 365));
        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $data[] = [
                'date' => $date,
                'revenue' => \plugin\admin\app\model\shop\Orders::whereDate('pay_at', $date)->sum('pay_amount') ?? 0,
                'orders' => \plugin\admin\app\model\shop\Orders::whereDate('created_at', $date)->count(),
                'new_users' => \plugin\admin\app\model\shop\Users::whereDate('created_at', $date)->count(),
            ];
        }
        return $this->json(['code' => 0, 'data' => $data]);
    }

    /**
 * @Apidoc\Title("KPI数据")
 * @Apidoc\Desc("今日订单/营收/用户")
 * @Apidoc\Method("GET")
 * @Apidoc\Url("/app/admin/shop/ShopDashboard/kpi")
 * @Apidoc\Author("erik")
 */
    public function kpi()
    {
        $today = date('Y-m-d');
        return $this->json(['code' => 0, 'data' => [
            'today_orders' => \plugin\admin\app\model\shop\Orders::whereDate('created_at', $today)->count(),
            'today_revenue' => \plugin\admin\app\model\shop\Orders::whereDate('pay_at', $today)->sum('pay_amount') ?? 0,
            'total_users' => \plugin\admin\app\model\shop\Users::count(),
            'total_products' => \plugin\admin\app\model\shop\Products::count(),
            'pending_reviews' => \plugin\admin\app\model\shop\ProductReviews::where('status', 0)->count(),
            'pending_returns' => \plugin\admin\app\model\shop\ReturnOrders::where('status', 0)->count(),
            'pending_risk' => \plugin\admin\app\model\shop\RiskLogs::where('result', 'review')->whereDate('created_at', $today)->count(),
            'refund_rate' => $this->calcRefundRate($today),
        ]]);
    }

    private function calcRefundRate(string $today): float
    {
        $total = \plugin\admin\app\model\shop\Orders::whereDate('created_at', $today)->count();
        if ($total === 0) return 0;
        $refunds = \plugin\admin\app\model\shop\Refunds::whereDate('created_at', $today)->where('status', 3)->count();
        return round($refunds / $total * 100, 2);
    }
}
