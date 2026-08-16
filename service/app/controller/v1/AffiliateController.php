<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\AffiliateLinks;
use app\model\AffiliateCommissions;
use Webman\Http\Request;

class AffiliateController extends \app\controller\BaseApiController
{
    public function links(Request $request): \support\Response
    {
        $links = AffiliateLinks::where('user_id', $request->userId)->where('status', 1)->get();
        return ApiResponse::success($links);
    }

    public function commissions(Request $request): \support\Response
    {
        [$page, $perPage] = $this->clampPage($request);

        $paginator = AffiliateCommissions::whereHas('link', fn($q) => $q->where('user_id', $request->userId))
            ->orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginate($paginator->items(), $paginator->total(), $page, $perPage);
    }
}
