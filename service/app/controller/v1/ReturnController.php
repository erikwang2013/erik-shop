<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\ReturnOrders;
use Webman\Http\Request;
class ReturnController extends \app\controller\BaseApiController
{
    public function index(Request $request): \support\Response {
        $items = ReturnOrders::where('user_id',$request->userId)->orderBy('id','desc')->get();
        return ApiResponse::success($items);
    }
    public function create(Request $request): \support\Response {
        $order = \app\model\Orders::where('id',$request->input('order_id'))->where('user_id',$request->userId)->first();
        if(!$order) return ApiResponse::fail('订单不存在',404);
        $r = ReturnOrders::create(['order_id'=>$order->id,'user_id'=>$request->userId,'return_no'=>'RET'.date('Ymd').strtoupper(substr(md5(uniqid()),0,6)),'status'=>0]);
        return ApiResponse::success(['return_id'=>$r->id,'return_no'=>$r->return_no],'已提交');
    }
    public function label(Request $request, string $id): \support\Response {
        return ApiResponse::success(['label_url'=>'','tracking_no'=>'']);
    }
}
