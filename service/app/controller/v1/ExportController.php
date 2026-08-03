<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\model\Orders;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Webman\Http\Request;

class ExportController extends \app\controller\BaseApiController
{
    /**
     * 导出订单（XLSX/CSV）
     * GET /api/export/orders?format=xlsx&date_from=2026-01-01&date_to=2026-06-01
     */
    public function orders(Request $request): \support\Response
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $format = $request->input('format', 'xlsx');

        $query = Orders::where('user_id', $request->userId)
            ->with('items');
        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }

        $orders = $query->orderBy('id', 'desc')->limit(1000)->get();

        if ($format === 'xlsx') {
            return $this->exportXlsx($orders);
        }

        return $this->exportCsv($orders);
    }

    private function exportXlsx($orders): \support\Response
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['订单号', '日期', '金额', '币种', '状态', '商品数', 'HS Code'];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        $statusMap = ['待付款', '已付款', '已发货', '已收货', '已完成', '已取消', '退款中', '已退款'];

        foreach ($orders as $o) {
            $items = $o->items ?? collect();
            $hsCodes = $items->pluck('hs_code')->filter()->unique()->implode(', ');
            $sheet->fromArray([
                $o->order_no,
                $o->created_at,
                $o->pay_amount,
                $o->currency_code,
                $statusMap[$o->status] ?? '未知',
                $items->count(),
                $hsCodes,
            ], null, "A{$row}");
            $row++;
        }

        $tmpFile = runtime_path() . '/orders_export.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpFile);

        return response()->file($tmpFile, 'orders.xlsx');
    }

    private function exportCsv($orders): \support\Response
    {
        $statusMap = ['待付款', '已付款', '已发货', '已收货', '已完成', '已取消'];
        $csv = "Order No,Date,Amount,Currency,Status\n";
        foreach ($orders as $o) {
            $statusText = $statusMap[$o->status] ?? '';
            $csv .= "{$o->order_no},{$o->created_at},{$o->pay_amount},{$o->currency_code},{$statusText}\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="orders.csv"',
        ]);
    }
}
