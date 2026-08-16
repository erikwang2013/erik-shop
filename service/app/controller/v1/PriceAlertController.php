<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\PriceAlerts;
use Webman\Http\Request;

class PriceAlertController extends \app\controller\BaseApiController
{
    public function index(Request $request): \support\Response
    {
        [$page, $perPage] = $this->clampPage($request);
        $paginator = PriceAlerts::where('user_id', $request->userId)->with('sku.product')->orderBy('id','desc')->paginate($perPage, ['*'], 'page', $page);
        return ApiResponse::paginate($paginator->items(), $paginator->total(), $page, $perPage);
    }

    public function store(Request $request): \support\Response
    {
        $skuId = $request->input('sku_id');
        $targetPrice = (float) $request->input('target_price', 0);

        PriceAlerts::updateOrCreate(
            ['user_id' => $request->userId, 'sku_id' => $skuId],
            ['target_price' => $targetPrice, 'is_notified' => 0]
        );

        return ApiResponse::success(null, '降价提醒已设置');
    }
}
