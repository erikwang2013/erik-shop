<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;
use app\common\ApiResponse;
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
        $exists = UserWishlists::where('user_id',$request->userId)->where('product_id',$productId)->exists();
        if ($exists) return ApiResponse::success(null, '已在收藏夹');

        UserWishlists::create(['user_id' => $request->userId, 'product_id' => $productId]);
        return ApiResponse::success(null, '已收藏');
    }

    public function destroy(Request $request, string $id): \support\Response
    {
        $id = $this->decodedId($id);
        UserWishlists::where('id',$id)->where('user_id',$request->userId)->delete();
        return ApiResponse::success(null, '已取消收藏');
    }
}
