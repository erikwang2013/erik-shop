<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\common\Money;
use app\model\GiftCards;
use app\model\Users;
use support\Db;
use Webman\Http\Request;

class GiftCardController extends \app\controller\BaseApiController
{
    public function balance(Request $request): \support\Response
    {
        $code = $request->input('code');
        $card = GiftCards::where('code', $code)->where('status', 1)->first();
        if (!$card) return ApiResponse::fail('礼品卡不存在或已失效', 404);
        if ($card->expire_at && $card->expire_at < date('Y-m-d')) return ApiResponse::fail('礼品卡已过期', 422);

        return ApiResponse::success([
            'code' => $card->code,
            'denomination' => $card->denomination,
            'balance' => $card->balance,
            'currency' => $card->currency_code,
            'expire_at' => $card->expire_at,
        ]);
    }

    public function redeem(Request $request): \support\Response
    {
        $code = $request->input('code');
        if ($code === '') return ApiResponse::fail('缺少礼品卡码', 422);

        // 事务 + 行锁 + 条件更新防并发双花；过期卡拒绝兑换
        return Db::transaction(function () use ($request, $code) {
            $card = GiftCards::where('code', $code)->where('status', 1)->lockForUpdate()->first();
            if (!$card) return ApiResponse::fail('礼品卡不存在或已使用', 404);
            if ($card->expire_at && $card->expire_at < date('Y-m-d')) return ApiResponse::fail('礼品卡已过期', 422);

            // 余额为 decimal 字符串，比较与入账均十进制
            $amount = (string) $card->balance;
            if (Money::cmp($amount, '0') <= 0) return ApiResponse::fail('礼品卡余额无效', 422);

            // 原子扣减：状态置 2 必须带条件更新，防止行锁竞态下重复入账
            $updated = GiftCards::where('id', $card->id)
                ->where('status', 1)
                ->update(['status' => 2]);

            if ($updated !== 1) return ApiResponse::fail('礼品卡已被兑换', 409);

            Users::where('id', $request->userId)->increment('money', $amount);

            // JSON 输出边界 (float) 展示转换
            return ApiResponse::success(['amount' => (float) $amount, 'currency' => $card->currency_code], '兑换成功');
        });
    }
}
