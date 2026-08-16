<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\FaqTranslations;
use Webman\Http\Request;

class FaqController extends \app\controller\BaseApiController
{
    public function index(Request $request): \support\Response
    {
        $locale = $request->locale ?? 'en';
        $category = $request->input('category');

        $query = FaqTranslations::where('locale', $locale)->where('status', 1);
        if ($category) $query->where('category', $category);

        $items = $query->orderBy('sort')->get();
        return ApiResponse::success($items);
    }
}
