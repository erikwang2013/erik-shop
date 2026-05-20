<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\FlashSales;
use app\model\FlashSaleSkus;
use Webman\Http\Request;

class FlashSaleController extends \app\controller\BaseApiController
{
    public function index(Request $request): \support\Response
    {
        $now = date('Y-m-d H:i:s');
        $sales = FlashSales::where('status', 1)
            ->where('start_at', '<=', $now)->where('end_at', '>=', $now)
            ->with(['skus.sku.product'])
            ->get()->map(function ($s) {
                $s->skus->transform(fn($sk) => [
                    'sku_id' => $sk->sku_id,
                    'product_id' => $sk->sku->product_id ?? 0,
                    'title' => $sk->sku->product->title ?? '',
                    'image' => $sk->sku->image,
                    'flash_price' => $sk->price,
                    'origin_price' => $sk->sku->default_price,
                    'stock' => $sk->stock,
                    'limit_per_user' => $sk->limit_per_user,
                ]);
                return $s;
            });
        return ApiResponse::success($sales);
    }
}
