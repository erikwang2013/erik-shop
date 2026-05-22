<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\FlashSales;
use Webman\Http\Request;
class FlashSaleController extends \app\controller\BaseApiController
{
    public function index(Request $request): \support\Response {
        $now = date('Y-m-d H:i:s');
        $sales = FlashSales::where('status',1)->where('start_at','<=',$now)->where('end_at','>=',$now)->with(['skus.sku.product'])->get();
        return ApiResponse::success($sales);
    }
}
