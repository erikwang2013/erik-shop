<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\ProductRecommendations;
use app\model\Products;
use Webman\Http\Request;

class RecommendationController extends \app\controller\BaseApiController
{
    public function index(Request $request): \support\Response
    {
        $userId = $request->userId;
        // TODO: 协同过滤
        // 降级: 热门推荐
        $hotProducts = Products::where('status', 2)->where('is_recommend', 1)
            ->orderBy('sales_count', 'desc')->limit(10)->get();

        return ApiResponse::success($hotProducts);
    }
}
