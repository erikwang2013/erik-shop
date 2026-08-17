<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;
use app\common\ApiResponse;
use app\common\DistributedLock;
use app\model\UserWishlists;
use Webman\Http\Request;

class WishlistController extends \app\controller\BaseApiController
{
    public function index(Request $request): \support\Response
    {
        $page = (int) $request->input('page', 1);
        $perPage = min((int) $request->input('per_page', 10), 50);
        $paginator = UserWishlists::where('user_id', $request->userId)
            ->with(['product.skus.prices'])
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
        return ApiResponse::paginate($paginator->items(), $paginator->total(), $page, $perPage);
    }

    public function store(Request $request): \support\Response
    {
        $productId = $request->input('product_id');
        // 用户+商品粒度锁：并发收藏会绕过 exists 检查触发唯一键冲突
        try {
            return DistributedLock::run("lock:wishlist:{$request->userId}:{$productId}", function () use ($request, $productId) {
                $exists = UserWishlists::where('user_id',$request->userId)->where('product_id',$productId)->exists();
                if ($exists) return ApiResponse::success(null, '已在收藏夹');

                UserWishlists::create(['user_id' => $request->userId, 'product_id' => $productId]);
                return ApiResponse::success(null, '已收藏');
            });
        } catch (\RuntimeException $e) {
            return ApiResponse::fail('操作繁忙，请稍后重试', 429);
        }
    }

    public function destroy(Request $request, string $id): \support\Response
    {
        $id = $this->decodedId($id);
        UserWishlists::where('id',$id)->where('user_id',$request->userId)->delete();
        return ApiResponse::success(null, '已取消收藏');
    }
}
