<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\ProductReviews;
use Webman\Http\Request;

class ReviewController extends \app\controller\BaseApiController
{
    public function index(Request $request, string $productId): \support\Response
    {
        $page = (int) $request->input('page', 1);
        $perPage = min((int) $request->input('per_page', 10), 50);
        $rating = $request->input('rating');

        $query = ProductReviews::where('product_id', $productId)->where('status', 1);
        if ($rating) $query->where('rating', $rating);

        $paginator = $query->orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);
        return ApiResponse::paginate($paginator->items(), $paginator->total(), $page, $perPage);
    }

    public function store(Request $request): \support\Response
    {
        $productId = $request->input('product_id');
        $orderId = $request->input('order_id', 0);
        $skuId = $request->input('sku_id', 0);
        $rating = (int) $request->input('rating', 5);
        $content = $request->input('content', '');
        $images = $request->input('images', []);

        if ($rating < 1 || $rating > 5) return ApiResponse::fail('评分范围为1-5', 422);

        $review = ProductReviews::create([
            'user_id' => $request->userId,
            'product_id' => $productId,
            'order_id' => $orderId,
            'sku_id' => $skuId,
            'rating' => $rating,
            'content' => $content,
            'images' => $images,
            'status' => 1,
        ]);

        return ApiResponse::success($review, '评价发表成功');
    }
}
