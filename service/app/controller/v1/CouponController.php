<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\Coupons;
use app\model\Orders;
use app\model\UserCoupons;
use Webman\Http\Request;

class CouponController extends \app\controller\BaseApiController
{
    public function available(Request $request): \support\Response
    {
        $country = $request->geoCountry ?? 'US';
        $now = date('Y-m-d H:i:s');

        $coupons = Coupons::where('status', 1)
            ->where('start_at', '<=', $now)
            ->where('end_at', '>=', $now)
            ->where(function ($q) use ($country) {
                $q->whereNull('countries')->orWhereJsonContains('countries', $country);
            })
            ->whereRaw('received_qty < total_qty')
            ->get();

        return ApiResponse::success($coupons);
    }

    public function claim(Request $request, string $id): \support\Response
    {
        $userId = $request->userId;
        $coupon = Coupons::find($id);
        if (!$coupon || $coupon->status !== 1) return ApiResponse::fail('优惠券不存在', 404);

        $claimedCount = UserCoupons::where('user_id', $userId)->where('coupon_id', $id)->count();
        if ($claimedCount >= $coupon->per_user_limit) return ApiResponse::fail('已达领取上限', 422);

        if ($coupon->received_qty >= $coupon->total_qty) return ApiResponse::fail('已被抢光', 422);

        if ($coupon->new_user_only) {
            $orderCount = Orders::where('user_id', $userId)->where('status', '>=', 1)->count();
            if ($orderCount > 0) return ApiResponse::fail('仅限新用户', 422);
        }

        UserCoupons::create(['user_id' => $userId, 'coupon_id' => $id]);
        $coupon->increment('received_qty');

        return ApiResponse::success(null, '领取成功');
    }
}
