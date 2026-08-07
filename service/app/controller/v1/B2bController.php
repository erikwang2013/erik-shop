<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\B2bVerifications;
use app\model\B2bQuotes;
use app\model\B2bPrices;
use Webman\Http\Request;

class B2bController extends \app\controller\BaseApiController
{
    public function index(Request $request): \support\Response
    {
        $verification = B2bVerifications::where('user_id', $request->userId)->first();
        if (!$verification || $verification->status !== 1) {
            return ApiResponse::fail('请先完成企业认证', 403);
        }

        $page = (int) $request->input('page', 1);
        $perPage = min((int) $request->input('per_page', 10), 50);
        $paginator = B2bQuotes::where('user_id', $request->userId)->orderBy('id','desc')->paginate($perPage, ['*'], 'page', $page);
        return ApiResponse::paginate($paginator->items(), $paginator->total(), $page, $perPage);
    }

    public function store(Request $request): \support\Response
    {
        $verification = B2bVerifications::where('user_id', $request->userId)->where('status',1)->first();
        if (!$verification) return ApiResponse::fail('请先完成企业认证', 403);

        $quote = B2bQuotes::create([
            'user_id' => $request->userId,
            'product_id' => $request->input('product_id'),
            'sku_id' => $request->input('sku_id', 0),
            'quantity' => $request->input('quantity'),
            'target_price' => $request->input('target_price', 0),
            'currency_code' => $request->input('currency_code', 'USD'),
        ]);

        return ApiResponse::success($quote, '询价已提交');
    }
}
