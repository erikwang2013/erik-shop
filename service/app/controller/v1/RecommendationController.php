<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\model\Products;
use Webman\Http\Request;

class RecommendationController extends \app\controller\BaseApiController
{
    public function index(Request $request): \support\Response
    {
        $userId = $request->userId;
        $limit = min((int)$request->input('limit', 10), 50);

        // Item-based collaborative filtering via purchase co-occurrence
        try {
            $recommended = $this->collaborativeFilter($userId, $limit);
            if (!empty($recommended)) {
                return ApiResponse::success($recommended);
            }
        } catch (\Throwable $e) {
            // Degrade to hot recommendations
        }

        // Fallback: hot + recommended products
        $hotProducts = Products::where('status', 2)
            ->where('is_recommend', 1)
            ->orderBy('sales_count', 'desc')
            ->limit($limit)
            ->get();

        return ApiResponse::success($hotProducts);
    }

    /**
     * Item-based collaborative filtering via purchase co-occurrence.
     * Finds products frequently bought together with the user's past purchases.
     */
    private function collaborativeFilter(int $userId, int $limit): array
    {
        // Get user's purchased product IDs
        $purchasedIds = \app\model\OrderItems::whereHas('order', fn($q) =>
            $q->where('user_id', $userId)->whereIn('status', [2, 3, 4])
        )->pluck('product_id')->unique()->toArray();

        if (empty($purchasedIds)) {
            return [];
        }

        // Find users who bought the same products
        $similarUserIds = \app\model\OrderItems::whereIn('product_id', $purchasedIds)
            ->whereHas('order', fn($q) => $q->where('user_id', '!=', $userId))
            ->pluck('order.user_id')->unique()->toArray();

        if (empty($similarUserIds)) {
            return [];
        }

        // Get products bought by similar users, excluding already purchased
        $recommendedIds = \app\model\OrderItems::whereHas('order', fn($q) =>
            $q->whereIn('user_id', $similarUserIds)->whereIn('status', [2, 3, 4])
        )->whereNotIn('product_id', $purchasedIds)
            ->selectRaw('product_id, COUNT(*) as score')
            ->groupBy('product_id')
            ->orderBy('score', 'desc')
            ->limit($limit)
            ->pluck('product_id')
            ->toArray();

        if (empty($recommendedIds)) {
            return [];
        }

        return Products::where('status', 2)
            ->whereIn('id', $recommendedIds)
            ->orderByRaw('FIELD(id, ' . implode(',', $recommendedIds) . ')')
            ->get()
            ->toArray();
    }
}
