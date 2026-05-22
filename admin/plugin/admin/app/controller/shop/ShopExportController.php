<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Base;
use plugin\admin\app\model\shop\Orders;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Barryvdh\DomPDF\Facade\Pdf;
use support\Request;

/**
 * @Apidoc\Group("general")
 * @Apidoc\Sort(7)
 */
class ShopExportController extends Base
{
    /**
 * @Apidoc\Title("导出订单Excel")
 * @Apidoc\Method("GET")
 * @Apidoc\Url("/app/admin/shop/ShopExportController/orders")
 * @Apidoc\Author("erik")
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

        // PhpSpreadsheet 生成 Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('订单导出');

        // 表头
        $headers = ['订单号', '日期', '状态', '币种', '商品金额', '优惠', '运费', '实付金额'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // 数据行
        $row = 2;
        $statusMap = ['待付款','已付款','已发货','已收货','已完成','已取消','退款中','已退款'];
        foreach ($orders as $o) {
            $sheet->fromArray([
                $o->order_no, $o->created_at,
                $statusMap[$o->status] ?? '未知', $o->currency_code,
                $o->total_amount, $o->discount_amount,
                $o->shipping_fee, $o->pay_amount,
            ], null, "A{$row}");
            $row++;
        }

        // 列宽自适应
        foreach (range('A', 'H') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="orders_' . date('Ymd') . '.xlsx"',
        ]);
    }

    /**
     * 商业发票PDF
     */
    public function invoice(Request $request, $orderId)
    {
        $order = Orders::with(['items'])->find($orderId);
        if (!$order) return $this->fail('订单不存在');

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            body{font-family:sans-serif;margin:40px} h1{font-size:20px} table{width:100%;border-collapse:collapse;margin:20px 0}
            th,td{border:1px solid #333;padding:8px;text-align:left} th{background:#f0f0f0}
            .header{display:flex;justify-content:space-between} .total{text-align:right;font-size:16px;font-weight:bold}
        </style></head><body>
            <h1>商业发票 / Commercial Invoice</h1>
            <div class="header"><div>
                <p><strong>发票号:</strong> INV-' . $order->order_no . '</p>
                <p><strong>日期:</strong> ' . $order->created_at . '</p>
                <p><strong>币种:</strong> ' . $order->currency_code . '</p>
            </div></div>
            <table><thead><tr><th>商品</th><th>数量</th><th>单价</th><th>小计</th></tr></thead><tbody>';

        foreach ($order->items as $item) {
            $html .= '<tr><td>' . $item->title . '</td><td>' . $item->quantity . '</td><td>' . number_format($item->price, 2) . '</td><td>' . number_format($item->subtotal, 2) . '</td></tr>';
        }

        $html .= '</tbody></table>
            <p class="total">总计: ' . number_format($order->pay_amount, 2) . ' ' . $order->currency_code . '</p>
            <p style="font-size:11px;color:#666">仅供海关申报使用，实际金额以订单为准。</p>
        </body></html>';

        $pdf = Pdf::loadHTML($html)->setPaper('A4');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="invoice_' . $order->order_no . '.pdf"',
        ]);
    }
}
