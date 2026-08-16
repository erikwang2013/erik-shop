<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\GroupBuys;
use Webman\Http\Request;

class GroupBuyController extends \app\controller\BaseApiController
{
    public function index(Request $request): \support\Response
    {
        $now = date('Y-m-d H:i:s');
        $list = GroupBuys::where('status', 1)
            ->where(function($q) use ($now) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })->where(function($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', $now);
            })->with(['sku.product'])->get()
            ->map(fn($g) => [
                'id' => $g->id,
                'title' => $g->title,
                'sku_id' => $g->sku_id,
                'product_title' => $g->sku->product->title ?? '',
                'image' => $g->sku->image,
                'group_price' => $g->group_price,
                'origin_price' => $g->sku->default_price,
                'required_count' => $g->required_count,
                'expire_hours' => $g->expire_hours,
            ]);
        return ApiResponse::success($list);
    }
}
