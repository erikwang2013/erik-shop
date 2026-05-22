<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\Orders;
use Webman\Http\Request;
class DocumentController extends \app\controller\BaseApiController
{
    public function invoice(Request $request, string $orderId): \support\Response {
        return ApiResponse::success(['message'=>'商业发票生成中'],'暂无');
    }
    public function packingList(Request $request, string $orderId): \support\Response {
        return ApiResponse::success(['message'=>'装箱单生成中'],'暂无');
    }
}
