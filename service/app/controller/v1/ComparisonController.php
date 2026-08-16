<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\ProductComparisons;
use Webman\Http\Request;

class ComparisonController extends \app\controller\BaseApiController
{
    public function index(Request $request): \support\Response
    {
        $items = ProductComparisons::where('user_id', $request->userId)->with('product')->get();
        return ApiResponse::success($items);
    }

    public function store(Request $request): \support\Response
    {
        $productId = $request->input('product_id');
        $count = ProductComparisons::where('user_id', $request->userId)->count();
        if ($count >= 4) ProductComparisons::where('user_id', $request->userId)->oldest()->first()->delete();

        ProductComparisons::create(['user_id' => $request->userId, 'product_id' => $productId]);
        return ApiResponse::success(null, '已添加对比');
    }

    public function destroy(Request $request, string $id): \support\Response
    {
        $id = $this->decodedId($id);
        ProductComparisons::where('id',$id)->where('user_id',$request->userId)->delete();
        return ApiResponse::success(null, '已移除');
    }
}
