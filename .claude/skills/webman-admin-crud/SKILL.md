---
name: webman-admin-crud
description: Use when adding management controllers to the shop-php admin panel — extends plugin\admin\app\controller\Crud, binds erik_ models, adds menu entries to plugin/admin/config/menu.php, and implements Excel/PDF export
---

# Webman-Admin CRUD 扩展

## Overview

扩展 webman-admin 的 Crud 基类来创建管理控制器。设置 `$model` 属性即可获得完整的增删改查、分页、搜索功能。所有新模型使用 `erik_` 前缀。

## When to Use

- 添加新的管理后台菜单和 CRUD 页面
- 扩展已有 CRUD 控制器（自定义查询/格式化/验证）
- 实现 Excel/PDF 导出功能

## Core Pattern

### 基础 CRUD 控制器

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller;

use plugin\admin\app\model\ShopProduct;

class ShopProductController extends Crud
{
    protected $model = ShopProduct::class;         // 绑定模型

    // 无需写任何方法，自动拥有 select/insert/update/delete
}
```

### 扩展 CRUD 钩子

```php
class ShopProductController extends Crud
{
    protected $model = ShopProduct::class;

    // 查询后格式化
    protected function afterQuery($items)
    {
        foreach ($items as $item) {
            $item->category_name = ShopCategory::find($item->category_id)->name ?? '';
            $item->status_text = ['草稿', '待审核', '已上架', '已下架'][$item->status] ?? '';
        }
    }

    // 插入前处理
    protected function insertInput($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $data;
    }

    // 更新前处理
    protected function updateInput($data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $data;
    }

    // 删除前校验
    protected function beforeDelete($id)
    {
        $orderCount = ShopOrderItem::where('product_id', $id)->count();
        if ($orderCount > 0) {
            return $this->fail('该商品有关联订单，无法删除');
        }
        return null;
    }
}
```

## 菜单注册

在 `plugin/admin/config/menu.php` 中添加：

```php
// 菜单结构
[
    'title' => '商城管理',                    // 菜单名
    'key' => 'shop',                         // 唯一标识
    'icon' => 'layui-icon-cart-simple',      // 图标
    'weight' => 750,                         // 排序权重（越大越靠前）
    'type' => 0,                             // 0=菜单组, 1=单页面
    'children' => [
        [
            'title' => '商品管理',
            'key' => 'plugin\\admin\\app\\controller\\ShopProductController',
            'icon' => 'layui-icon-list',
            'weight' => 100,
            'type' => 1,                     // 叶节点
        ],
    ],
],
```

## 数据导出

### Excel 导出

```php
class ShopExportController extends Crud
{
    protected $model = ShopOrder::class;

    public function exportOrders(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $orders = ShopOrder::whereBetween('created_at', [$dateFrom, $dateTo])
            ->with('items')
            ->get();

        return Excel::download($orders, 'orders_' . date('Ymd') . '.xlsx', function ($excel) {
            // 列定义
        });
    }
}
```

### PDF 导出

```php
public function exportInvoice(Request $request, $orderId)
{
    $order = ShopOrder::with(['items', 'user'])->find($orderId);
    $pdf = Pdf::loadView('shop.invoice', ['order' => $order]);
    return $pdf->download('invoice_' . $order->order_no . '.pdf');
}
```

## 面板可视化

```php
class ShopDashboardController
{
    public function index()
    {
        return view('shop/dashboard', [
            'todayOrders' => ShopOrder::whereDate('created_at', today())->count(),
            'todayRevenue' => ShopOrder::whereDate('created_at', today())->sum('pay_amount'),
            'totalUsers' => User::count(),
            'trendData' => $this->getTrendData(),  // ECharts 数据
        ]);
    }

    // API 返回图表数据
    public function chartData(Request $request)
    {
        $days = $request->input('days', 7);
        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $data[] = [
                'date' => $date,
                'revenue' => ShopOrder::whereDate('paid_at', $date)->sum('pay_amount'),
                'orders' => ShopOrder::whereDate('created_at', $date)->count(),
            ];
        }
        return $this->json(['code' => 0, 'data' => $data]);
    }
}
```

## 可覆盖的 Crud 钩子

| 钩子 | 时机 | 说明 |
|------|------|------|
| `selectInput($data)` | 查询前 | 修改查询条件 |
| `afterQuery($items)` | 查询后 | 格式化列表数据 |
| `insertInput($data)` | 插入前 | 修改插入数据 |
| `updateInput($data)` | 更新前 | 修改更新数据 |
| `beforeDelete($id)` | 删除前 | 校验是否可删除 |
| `formatSelect($items)` | 列表 | 格式化 select 返回 |

## Common Mistakes

- **忘记设置 $model**：Crud 依赖此属性，不设置会报错
- **模型不在 plugin/admin 命名空间**：管理模型需要 `plugin\admin\app\model` 命名空间
- **跳过 beforeDelete 校验**：删除前必须检查关联数据
- **硬编码字段名**：表格列定义应使用翻译键值 `trans('shop.field_name')`
