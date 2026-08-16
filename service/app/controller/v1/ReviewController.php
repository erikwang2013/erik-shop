<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\OrderItems;
use app\model\Orders;
use app\model\ProductReviews;
use Webman\Http\Request;

class ReviewController extends \app\controller\BaseApiController
{
    public function index(Request $request, string $productId): \support\Response
    {
        $productId = $this->decodedId($productId);
        [$page, $perPage] = $this->clampPage($request);
        $rating = $request->input('rating');

        $query = ProductReviews::where('product_id', $productId)->where('status', 1);
        if ($rating) $query->where('rating', $rating);

        $paginator = $query->orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);
        return ApiResponse::paginate($paginator->items(), $paginator->total(), $page, $perPage);
    }

    public function store(Request $request): \support\Response
    {
        $userId = $request->userId;
        $productId = $request->input('product_id');
        $orderId = (int) $request->input('order_id', 0);
        $skuId = $request->input('sku_id', 0);
        $rating = (int) $request->input('rating', 5);
        $content = trim((string) $request->input('content', ''));
        $images = $request->input('images', []);

        if ($rating < 1 || $rating > 5) return ApiResponse::fail('评分范围为1-5', 422);
        if (empty($productId)) return ApiResponse::fail('商品ID不能为空', 422);
        if (mb_strlen($content) < 1 || mb_strlen($content) > 500) return ApiResponse::fail('评价内容长度为1-500字', 422);
        if (!is_array($images)) $images = [];

        // 购买校验：关联订单必须属于当前用户且包含该商品，并防重复评价
        if ($orderId > 0) {
            $order = Orders::where('id', $orderId)->where('user_id', $userId)->first();
            if (!$order) {
                return ApiResponse::fail('订单不存在或不属于当前用户', 422);
            }
            $hasItem = OrderItems::where('order_id', $orderId)->where('product_id', $productId)->exists();
            if (!$hasItem) {
                return ApiResponse::fail('该订单未包含此商品', 422);
            }
            $dup = ProductReviews::where('user_id', $userId)
                ->where('product_id', $productId)
                ->where('order_id', $orderId)
                ->exists();
            if ($dup) {
                return ApiResponse::fail('该订单商品已评价过', 422);
            }
        }

        $review = ProductReviews::create([
            'user_id' => $userId,
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
