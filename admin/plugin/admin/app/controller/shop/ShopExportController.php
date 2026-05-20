<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Base;
use plugin\admin\app\model\shop\Orders;
use support\Request;

class ShopExportController extends Base
{
    /**
     * 导出订单Excel（含HS Code/关税/币种）
     */
    public function orders(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $status = $request->input('status');

        $query = Orders::with(['items']);
        if ($dateFrom) $query->where('created_at', '>=', $dateFrom);
        if ($dateTo) $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        if ($status !== null && $status !== '') $query->where('status', (int) $status);

        $orders = $query->orderBy('id', 'desc')->limit(5000)->get();

        // TODO: PhpSpreadsheet Excel导出
        $csv = "Order No,Date,Status,Currency,Total,Discount,Shipping,Paid\n";
        foreach ($orders as $o) {
            $statusText = ['待付款','已付款','已发货','已收货','已完成','已取消','退款中','已退款'][$o->status] ?? '';
            $csv .= "{$o->order_no},{$o->created_at},{$statusText},{$o->currency_code},{$o->total_amount},{$o->discount_amount},{$o->shipping_fee},{$o->pay_amount}\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="orders_' . date('Ymd') . '.csv"',
        ]);
    }

    /**
     * 商业发票PDF
     */
    public function invoice(Request $request, $orderId)
    {
        $order = Orders::with(['items'])->find($orderId);
        if (!$order) return $this->fail('订单不存在');

        // TODO: DomPDF生成PDF
        return $this->json(['code' => 0, 'msg' => 'PDF generation placeholder', 'data' => ['order_no' => $order->order_no]]);
    }
}
