<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\ReturnOrders;
use app\model\ReturnLabels;
use app\model\Orders;
use app\model\Warehouses;
use Webman\Http\Request;

class ReturnController extends \app\controller\BaseApiController
{
    public function index(Request $request): \support\Response
    {
        $items = ReturnOrders::where('user_id', $request->userId)->orderBy('id','desc')->get();
        return ApiResponse::success($items);
    }

    public function create(Request $request): \support\Response
    {
        $orderId = $request->input('order_id');
        $reasonId = $request->input('reason_id', 0);
        $order = Orders::where('id',$orderId)->where('user_id',$request->userId)->first();
        if (!$order) return ApiResponse::fail('订单不存在', 404);
        if (!in_array($order->status, [2,3,4])) return ApiResponse::fail('订单状态不支持退货', 422);

        // 查找最近退货仓
        $addressSnapshot = $order->address_snapshot;
        $countryId = $addressSnapshot['country_id'] ?? 0;
        $returnWarehouse = Warehouses::where('country_id', $countryId)->where('is_return_warehouse', 1)->first();

        $return = ReturnOrders::create([
            'order_id' => $order->id,
            'user_id' => $request->userId,
            'return_no' => 'RET' . date('Ymd') . strtoupper(substr(md5(uniqid()),0,6)),
            'reason_id' => $reasonId,
            'type' => $returnWarehouse ? 1 : 2,
            'return_warehouse_id' => $returnWarehouse->id ?? 0,
            'status' => 0,
        ]);

        return ApiResponse::success(['return_id'=>$return->id, 'return_no'=>$return->return_no, 'return_type'=>$return->type], '退货申请已提交');
    }

    public function label(Request $request, string $id): \support\Response
    {
        $return = ReturnOrders::where('id',$id)->where('user_id',$request->userId)->first();
        if (!$return) return ApiResponse::fail('退货单不存在', 404);

        $label = ReturnLabels::where('return_id', $return->id)->first();
        if (!$label) return ApiResponse::fail('退货面单尚未生成', 404);

        return ApiResponse::success(['label_url'=>$label->label_url,'tracking_no'=>$label->tracking_no]);
    }
}
