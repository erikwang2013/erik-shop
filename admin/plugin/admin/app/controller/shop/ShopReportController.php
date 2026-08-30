<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Base;
use plugin\admin\app\model\Rule;
use plugin\admin\app\model\shop\OrderItems;
use plugin\admin\app\model\shop\Orders;
use plugin\admin\app\model\shop\Products;
use plugin\admin\app\model\shop\Users;
use support\Request;
use support\Response;

/**
 * 报表中心：销售摘要 / 趋势 / TOP商品 / 支付方式 / 订单状态
 * @Apidoc\Group("report")
 * @Apidoc\Sort(30)
 */
class ShopReportController extends Base
{
    const KEY = 'plugin\\admin\\app\\controller\\shop\\ShopReportController';

    const STATUS_TEXT = ['待付款', '已付款', '已发货', '已收货', '已完成', '已取消', '退款中', '已退款', '待审核'];

    /**
     * 报表页面
     * @Apidoc\Title("报表中心")
     * @Apidoc\Desc("GMV/订单趋势与分布可视化")
     * @Apidoc\Method("GET")
     * @Apidoc\Url("/app/admin/shop/ShopReport/index")
     * @Apidoc\Author("erik")
     */
    public function index(Request $request): Response
    {
        $this->registerMenus();
        return view('shop/report', ['title' => '报表中心']);
    }

    /**
     * 销售摘要
     * @Apidoc\Title("销售摘要")
     * @Apidoc\Desc("今日/昨日/近7天/近30天 GMV与订单数，总用户/商品/订单")
     * @Apidoc\Method("GET")
     * @Apidoc\Url("/app/admin/shop/ShopReport/summary")
     * @Apidoc\Author("erik")
     */
    public function summary(): Response
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $data = [
            'today' => $this->periodStats("$today 00:00:00"),
            'yesterday' => $this->periodStats("$yesterday 00:00:00", "$today 00:00:00"),
            'day7' => $this->periodStats(date('Y-m-d H:i:s', strtotime('-7 days'))),
            'day30' => $this->periodStats(date('Y-m-d H:i:s', strtotime('-30 days'))),
            'totals' => [
                'users' => Users::count(),
                'products' => Products::count(),
                'orders' => Orders::count(),
            ],
        ];
        return $this->json(0, 'ok', $data);
    }

    /**
     * 近30天销售趋势
     * @Apidoc\Title("销售趋势")
     * @Apidoc\Desc("每日 [date, gmv, orders]")
     * @Apidoc\Method("GET")
     * @Apidoc\Url("/app/admin/shop/ShopReport/trend")
     * @Apidoc\Author("erik")
     */
    public function trend(): Response
    {
        $data = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $end = date('Y-m-d', strtotime((1 - $i) . ' days')) . ' 00:00:00';
            $stats = $this->periodStats("$date 00:00:00", $end);
            $data[] = [$date, $stats['gmv'], $stats['orders']];
        }
        return $this->json(0, 'ok', $data);
    }

    /**
     * 销量TOP10商品
     * @Apidoc\Title("TOP商品")
     * @Apidoc\Desc("按订单明细销量/销售额排序")
     * @Apidoc\Method("GET")
     * @Apidoc\Url("/app/admin/shop/ShopReport/topProducts")
     * @Apidoc\Author("erik")
     */
    public function topProducts(): Response
    {
        $items = OrderItems::query()
            ->leftJoin('shop_products', 'shop_products.id', '=', 'shop_order_items.product_id')
            ->selectRaw('shop_order_items.product_id, MAX(COALESCE(shop_products.title, shop_order_items.title)) AS title, SUM(shop_order_items.quantity) AS quantity, SUM(shop_order_items.subtotal) AS revenue')
            ->groupBy('shop_order_items.product_id')
            ->orderByDesc('quantity')
            ->limit(10)
            ->get()
            ->map(fn ($i) => [
                'product_id' => (int) $i->product_id,
                'title' => (string) $i->title,
                'quantity' => (int) $i->quantity,
                'revenue' => round((float) $i->revenue, 2),
            ])
            ->values()
            ->all();
        return $this->json(0, 'ok', $items);
    }

    /**
     * 支付方式分布
     * @Apidoc\Title("支付方式分布")
     * @Apidoc\Desc("按订单支付方式统计订单数与金额")
     * @Apidoc\Method("GET")
     * @Apidoc\Url("/app/admin/shop/ShopReport/paymentMethods")
     * @Apidoc\Author("erik")
     */
    public function paymentMethods(): Response
    {
        $items = Orders::query()->whereNull('deleted_at')
            ->selectRaw('pay_method, COUNT(*) AS orders, SUM(total_amount) AS amount')
            ->groupBy('pay_method')
            ->orderByDesc('orders')
            ->get()
            ->map(fn ($i) => [
                'method' => $i->pay_method ?: '未知',
                'orders' => (int) $i->orders,
                'amount' => round((float) $i->amount, 2),
            ])
            ->values()
            ->all();
        return $this->json(0, 'ok', $items);
    }

    /**
     * 订单状态分布
     * @Apidoc\Title("订单状态分布")
     * @Apidoc\Desc("按订单状态分组计数")
     * @Apidoc\Method("GET")
     * @Apidoc\Url("/app/admin/shop/ShopReport/orderStatus")
     * @Apidoc\Author("erik")
     */
    public function orderStatus(): Response
    {
        $items = Orders::query()->whereNull('deleted_at')
            ->selectRaw('status, COUNT(*) AS count')
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($i) => [
                'status' => (int) $i->status,
                'name' => self::STATUS_TEXT[$i->status] ?? "状态{$i->status}",
                'count' => (int) $i->count,
            ])
            ->values()
            ->all();
        return $this->json(0, 'ok', $items);
    }

    /**
     * 区间订单统计（GMV 排除已取消状态与软删除订单）
     */
    private function periodStats(string $start, ?string $end = null): array
    {
        $query = Orders::query()->whereNull('deleted_at')
            ->where('status', '<>', 5)
            ->where('created_at', '>=', $start);
        if ($end !== null) {
            $query->where('created_at', '<', $end);
        }
        $row = $query->selectRaw('SUM(total_amount) AS gmv, COUNT(*) AS orders')->first();
        return ['gmv' => round((float) ($row->gmv ?? 0), 2), 'orders' => (int) ($row->orders ?? 0)];
    }

    /**
     * 幂等注册 wa_rules 菜单：类规则（父，type=1）+ index 方法规则（子，type=2）
     * 用查询构造器写入以绕开模型事件（Base 的 snowflake 钩子不适用于 wa_rules 自增主键）
     */
    private function registerMenus(): void
    {
        $now = date('Y-m-d H:i:s');
        $parentId = Rule::where('key', self::KEY)->value('id');
        if (!$parentId) {
            $parentId = Rule::query()->insertGetId([
                'title' => '报表中心',
                'icon' => 'layui-icon-chart-screen',
                'key' => self::KEY,
                'pid' => 0,
                'type' => 1,
                'href' => '/app/admin/shop/ShopReport/index',
                'weight' => 650,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $childKey = self::KEY . '::index';
        if (!Rule::where('key', $childKey)->value('id')) {
            Rule::query()->insertGetId([
                'title' => '报表中心',
                'key' => $childKey,
                'pid' => $parentId,
                'type' => 2,
                'weight' => 100,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
