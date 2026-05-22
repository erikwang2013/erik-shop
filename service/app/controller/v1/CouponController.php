<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\Coupons;
use app\model\UserCoupons;
use Webman\Http\Request;
class CouponController extends \app\controller\BaseApiController
{
    public function available(Request $request): \support\Response {
        $coupons = Coupons::where('status',1)->where('start_at','<=',date('Y-m-d H:i:s'))->where('end_at','>=',date('Y-m-d H:i:s'))->whereRaw('received_qty < total_qty')->get();
        return ApiResponse::success($coupons);
    }
    public function claim(Request $request, string $id): \support\Response {
        $coupon = Coupons::find($id);
        if(!$coupon||$coupon->status!==1) return ApiResponse::fail('不存在',404);
        $count = UserCoupons::where('user_id',$request->userId)->where('coupon_id',$id)->count();
        if($count>=$coupon->per_user_limit) return ApiResponse::fail('已达上限',422);
        if($coupon->received_qty>=$coupon->total_qty) return ApiResponse::fail('已抢光',422);
        UserCoupons::create(['user_id'=>$request->userId,'coupon_id'=>$id]);
        $coupon->increment('received_qty');
        return ApiResponse::success(null,'领取成功');
    }
}
