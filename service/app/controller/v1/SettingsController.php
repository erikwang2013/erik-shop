<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\Settings;
use Webman\Http\Request;

class SettingsController extends \app\controller\BaseApiController
{
    public function public(Request $request): \support\Response
    {
        $group = $request->input('group', 'general');
        $settings = Settings::where('group', $group)->pluck('value', 'key');
        return ApiResponse::success($settings);
    }
}
