<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\model\Coupons;
use app\model\Orders;
use app\model\UserCoupons;
use support\Db;
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

        try {
            Db::transaction(function () use ($userId, $id) {
                // 行锁串行化同一优惠券的并发领取，防止超发与超领
                $coupon = Coupons::where('id', $id)->lockForUpdate()->first();
                if (!$coupon || $coupon->status !== 1) {
                    throw new \RuntimeException('优惠券不存在');
                }

                $claimedCount = UserCoupons::where('user_id', $userId)->where('coupon_id', $id)->count();
                if ($claimedCount >= $coupon->per_user_limit) {
                    throw new \RuntimeException('已达领取上限');
                }

                if ($coupon->new_user_only) {
                    $orderCount = Orders::where('user_id', $userId)->where('status', '>=', 1)->count();
                    if ($orderCount > 0) {
                        throw new \RuntimeException('仅限新用户');
                    }
                }

                // 原子门闩：received_qty < total_qty 才递增
                $affected = Coupons::where('id', $id)
                    ->whereColumn('received_qty', '<', 'total_qty')
                    ->increment('received_qty');
                if (!$affected) {
                    throw new \RuntimeException('已被抢光');
                }

                UserCoupons::create(['user_id' => $userId, 'coupon_id' => $id]);
            });
        } catch (\RuntimeException $e) {
            $code = match ($e->getMessage()) {
                '优惠券不存在' => 404,
                default => 422,
            };
            return ApiResponse::fail($e->getMessage(), $code);
        } catch (\Throwable $e) {
            \support\Log::error('优惠券领取失败: ' . $e->getMessage(), [
                'user_id' => $userId,
                'coupon_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::fail('领取失败，请稍后重试', 500);
        }

        return ApiResponse::success(null, '领取成功');
    }
}
