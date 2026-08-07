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
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = ['订单号', '日期', '金额', '币种', '状态', '商品数', 'HS Code'];
            $sheet->fromArray($headers, null, 'A1');

            $row = 2;
            $statusMap = ['待付款', '已付款', '已发货', '已收货', '已完成', '已取消', '退款中', '已退款', '待审核'];

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

            // 唯一文件名避免并发导出互相覆盖，发送后清理临时文件
            $tmpFile = runtime_path() . '/orders_export_' . uniqid() . '.xlsx';
            $writer = new Xlsx($spreadsheet);
            $writer->save($tmpFile);
            register_shutdown_function(fn() => @unlink($tmpFile));

            return response()->download($tmpFile, 'orders.xlsx');
        } catch (\Throwable $e) {
            \support\Log::error('订单导出失败: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ApiResponse::fail('导出失败，请稍后重试', 500);
        }
    }

    private function exportCsv($orders): \support\Response
    {
        try {
            $statusMap = ['待付款', '已付款', '已发货', '已收货', '已完成', '已取消', '退款中', '已退款', '待审核'];
            $csv = "Order No,Date,Amount,Currency,Status\n";
            foreach ($orders as $o) {
                $statusText = $statusMap[$o->status] ?? '';
                $csv .= implode(',', [
                    $this->csvField($o->order_no),
                    $this->csvField($o->created_at),
                    $this->csvField($o->pay_amount),
                    $this->csvField($o->currency_code),
                    $this->csvField($statusText),
                ]) . "\n";
            }

            return response($csv, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="orders.csv"',
            ]);
        } catch (\Throwable $e) {
            \support\Log::error('订单 CSV 导出失败: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ApiResponse::fail('导出失败，请稍后重试', 500);
        }
    }

    // 转义 CSV 字段；以 = + - @ 开头的值加单引号前缀，防止 Excel 公式注入
    private function csvField(mixed $value): string
    {
        $value = (string)($value ?? '');
        if (preg_match('/^[=+\-@]/', $value)) {
            $value = "'" . $value;
        }
        if (preg_match('/[",\r\n]/', $value)) {
            $value = '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}
