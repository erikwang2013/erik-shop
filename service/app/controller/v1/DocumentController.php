<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\model\Orders;
use app\model\OrderDocuments;
use Webman\Http\Request;

/**
 * 订单单据：商业发票 / 装箱单 PDF（dompdf 按需生成 + 落库）
 *
 * 背景：DocumentController 此前仅读取已有 erik_order_documents 记录，无生成逻辑
 * （docs/PLAN-RESEARCH.md §7 差距：全项目零 Dompdf 调用）。本控制器按需生成 PDF
 * 并写入 erik_order_documents（幂等：已生成直接返回 file_url）。
 */
class DocumentController extends \app\controller\BaseApiController
{
    /**
     * 商业发票 PDF
     * GET /api/orders/{id}/documents/invoice
     */
    public function invoice(Request $request, string $id): \support\Response
    {
        $orderId = $this->decodedId($id);
        $order = Orders::with(['items'])->where('id', $orderId)->where('user_id', $request->userId)->first();
        if (!$order) {
            return ApiResponse::fail('订单不存在', 404);
        }
        return $this->getOrGenerate($order, 'invoice', '商业发票', $this->buildInvoiceHtml($order));
    }

    /**
     * 装箱单 PDF
     * GET /api/orders/{id}/documents/packing-list
     */
    public function packingList(Request $request, string $id): \support\Response
    {
        $orderId = $this->decodedId($id);
        $order = Orders::with(['items'])->where('id', $orderId)->where('user_id', $request->userId)->first();
        if (!$order) {
            return ApiResponse::fail('订单不存在', 404);
        }
        return $this->getOrGenerate($order, 'packing_list', '装箱单', $this->buildPackingListHtml($order));
    }

    /**
     * 幂等：已生成直接返回 file_url，否则生成 PDF + 落库
     */
    private function getOrGenerate(Orders $order, string $type, string $title, string $html): \support\Response
    {
        $doc = OrderDocuments::where('order_id', $order->id)->where('type', $type)->first();
        if ($doc && is_file(public_path() . $doc->file_path)) {
            return ApiResponse::success(['file_url' => $doc->file_path, 'generated_at' => $doc->generated_at]);
        }

        try {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4');
            $dompdf->render();

            $dir = public_path() . '/documents';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $fileName = $order->order_no . '_' . $type . '.pdf';
            @file_put_contents($dir . '/' . $fileName, $dompdf->output());

            if (!$doc) {
                OrderDocuments::create([
                    'order_id' => $order->id,
                    'type' => $type,
                    'file_path' => '/documents/' . $fileName,
                    'generated_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                $doc->file_path = '/documents/' . $fileName;
                $doc->generated_at = date('Y-m-d H:i:s');
                $doc->save();
            }

            return ApiResponse::success(['file_url' => '/documents/' . $fileName, 'generated_at' => date('Y-m-d H:i:s')], $title . '已生成');
        } catch (\Throwable $e) {
            \support\Log::error($title . '生成失败: ' . $e->getMessage(), ['order_id' => $order->id]);
            return ApiResponse::fail($title . '生成失败，请稍后重试', 500);
        }
    }

    private function buildInvoiceHtml(Orders $order): string
    {
        $rows = '';
        foreach ($order->items as $item) {
            $rows .= '<tr>'
                . '<td>' . htmlspecialchars((string) $item->title) . '</td>'
                . '<td style="text-align:center">' . (int) $item->quantity . '</td>'
                . '<td style="text-align:right">' . number_format((float) $item->price, 2) . '</td>'
                . '<td style="text-align:right">' . number_format((float) $item->subtotal, 2) . '</td>'
                . '</tr>';
        }
        $cur = $order->currency_code ?: 'USD';
        return $this->wrapHtml('商业发票 INVOICE', $order, $rows, $cur, [
            '商品金额' => (float) $order->total_amount,
            '优惠' => (float) $order->discount_amount,
            '运费' => (float) $order->shipping_fee,
            '关税/VAT' => (float) $order->tax_amount,
            '应付金额' => (float) $order->pay_amount,
        ]);
    }

    private function buildPackingListHtml(Orders $order): string
    {
        $rows = '';
        foreach ($order->items as $item) {
            $rows .= '<tr>'
                . '<td>' . htmlspecialchars((string) $item->title) . '</td>'
                . '<td style="text-align:center">' . (int) $item->quantity . '</td>'
                . '<td style="text-align:right">' . number_format((float) $item->price, 2) . '</td>'
                . '</tr>';
        }
        $cur = $order->currency_code ?: 'USD';
        return $this->wrapHtml('装箱单 PACKING LIST', $order, $rows, $cur, [
            '商品金额' => (float) $order->total_amount,
            '应付金额' => (float) $order->pay_amount,
        ]);
    }

    private function wrapHtml(string $title, Orders $order, string $rows, string $cur, array $totals): string
    {
        $summary = '';
        foreach ($totals as $label => $value) {
            $summary .= '<tr><td>' . $label . '</td><td style="text-align:right">' . number_format($value, 2) . ' ' . $cur . '</td></tr>';
        }
        $addr = is_array($order->address_snapshot) ? $order->address_snapshot : [];
        $addrLine = trim(($addr['detail'] ?? '') . ' ' . ($addr['city'] ?? '') . ' ' . ($addr['province'] ?? ''));
        return '<html><head><meta charset="UTF-8"><style>'
            . 'body{font-family:DejaVu Sans,sans-serif;font-size:12px}'
            . 'h1{font-size:20px;border-bottom:2px solid #333;padding-bottom:6px}'
            . 'table{width:100%;border-collapse:collapse;margin-top:12px}'
            . 'td,th{border:1px solid #999;padding:6px 8px;text-align:left}'
            . 'th{background:#f0f0f0}'
            . '.meta{color:#555;margin-top:8px}'
            . '</style></head><body>'
            . '<h1>' . $title . '</h1>'
            . '<div class="meta">订单号: ' . $order->order_no . ' | 下单时间: ' . $order->created_at . ' | 币种: ' . $cur . '</div>'
            . '<div class="meta">收货: ' . htmlspecialchars($addrLine) . '</div>'
            . '<table><thead><tr><th>商品</th><th>数量</th><th>单价</th><th>小计</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table>'
            . '<table style="width:50%;margin-left:50%"><tbody>' . $summary . '</tbody></table>'
            . '<p class="meta">本单据仅作海关申报/商业用途，实际税费以海关核定为准。</p>'
            . '</body></html>';
    }
}
