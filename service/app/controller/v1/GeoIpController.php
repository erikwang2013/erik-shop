<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;
use app\common\ApiResponse;
use Webman\Http\Request;

class GeoIpController extends \app\controller\BaseApiController
{
    public function detect(Request $request): \support\Response
    {
        return ApiResponse::success([
            'country' => $request->geoCountry ?? 'US',
            'currency' => $request->geoCurrency ?? 'USD',
            'language' => $request->geoLanguage ?? 'en',
        ]);
    }
}
