<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\Banners;
use Webman\Http\Request;

class BannerController extends \app\controller\BaseApiController
{
    public function index(Request $request): \support\Response
    {
        $position = $request->input('position', 'home');
        $country = $request->geoCountry ?? 'US';
        $now = date('Y-m-d H:i:s');

        $banners = Banners::where('position', $position)
            ->where('status', 1)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', $now);
            })
            ->where(function ($q) use ($country) {
                $q->whereNull('countries')->orWhereJsonContains('countries', $country);
            })
            ->orderBy('sort')->get();

        return ApiResponse::success($banners);
    }
}
