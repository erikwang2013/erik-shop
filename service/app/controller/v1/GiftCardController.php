<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\GiftCards;
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
        $card = GiftCards::where('code',$code)->where('status',1)->first();
        if (!$card) return ApiResponse::fail('礼品卡不存在', 404);

        // 充值到用户余额
        Users::where('id', $request->userId)->increment('money', $card->balance);
        $card->status = 2;  // 已用完
        $card->save();

        return ApiResponse::success(['amount' => $card->balance, 'currency' => $card->currency_code], '兑换成功');
    }
}
