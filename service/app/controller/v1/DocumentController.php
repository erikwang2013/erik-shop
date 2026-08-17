<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\model\Orders;
use app\model\OrderDocuments;
use Webman\Http\Request;
use Webman\RedisQueue\Redis;

/**
 * 订单单据：商业发票 / 装箱单 PDF（异步生成：入队 document_pdf 由消费者生成落库）
 *
 * 幂等：已生成直接返回 file_url + status=done；未生成则入队并返回 status=processing，
 * 前端稍后刷新即重新查询（文件生成后命中 done 分支）。生成失败时消费者记日志，
 * 用户重新请求即重新入队，天然重试。
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
        return $this->getOrGenerate($order, 'invoice', '商业发票');
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
        return $this->getOrGenerate($order, 'packing_list', '装箱单');
    }

    /**
     * 幂等：已生成直接返回 file_url，否则推队列异步生成并返回 processing
     */
    private function getOrGenerate(Orders $order, string $type, string $title): \support\Response
    {
        $doc = OrderDocuments::where('order_id', $order->id)->where('type', $type)->first();
        if ($doc && is_file(public_path() . $doc->file_path)) {
            return ApiResponse::success([
                'file_url' => $doc->file_path,
                'generated_at' => $doc->generated_at,
                'status' => 'done',
            ]);
        }

        $queued = Redis::send('document_pdf', [
            'order_id' => $order->id,
            'type' => $type,
            'user_id' => $order->user_id,
        ]);
        if (!$queued) {
            \support\Log::error($title . '入队失败', ['order_id' => $order->id]);
            return ApiResponse::fail($title . '生成失败，请稍后重试', 500);
        }

        return ApiResponse::success(['status' => 'processing'], $title . '生成中，稍后刷新获取');
    }
}
