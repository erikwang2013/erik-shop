<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\Orders;
use app\model\OrderDocuments;
use Webman\Http\Request;

class DocumentController extends \app\controller\BaseApiController
{
    public function invoice(Request $request, string $orderId): \support\Response
    {
        $order = Orders::with(['items','addressSnapshot'])->where('id',$orderId)->where('user_id',$request->userId)->first();
        if (!$order) return ApiResponse::fail('订单不存在', 404);

        $doc = OrderDocuments::where('order_id',$order->id)->where('type','invoice')->first();
        if ($doc) return ApiResponse::success(['file_url' => $doc->file_path]);

        return ApiResponse::success(['message' => '发票尚未生成，请联系客服'], '暂无');
    }

    public function packingList(Request $request, string $orderId): \support\Response
    {
        $order = Orders::where('id',$orderId)->where('user_id',$request->userId)->first();
        if (!$order) return ApiResponse::fail('订单不存在', 404);

        $doc = OrderDocuments::where('order_id',$order->id)->where('type','packing_list')->first();
        if ($doc) return ApiResponse::success(['file_url' => $doc->file_path]);

        return ApiResponse::success(['message' => '装箱单尚未生成'], '暂无');
    }
}
