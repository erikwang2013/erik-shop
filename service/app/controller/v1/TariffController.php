<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\TariffRules;
use app\model\VatSettings;
use app\model\ProductHsCodes;
use Webman\Http\Request;

class TariffController extends \app\controller\BaseApiController
{
    /**
     * 关税+增值税估算
     * GET /api/tariff/estimate?product_id=xxx&dest_country_id=xxx&declared_value=100
     */
    public function estimate(Request $request): \support\Response
    {
        $productId = $request->input('product_id');
        $destCountryId = $request->input('dest_country_id');
        $declaredValue = (float) $request->input('declared_value', 0);

        $hsCodeIds = ProductHsCodes::where('product_id', $productId)->pluck('hs_code_id');
        if ($hsCodeIds->isEmpty()) {
            return ApiResponse::success([
                'estimated_duty' => 0, 'estimated_vat' => 0, 'estimated_total' => 0,
                'message' => '该商品未关联HS Code，无法预估关税',
            ]);
        }

        $rule = TariffRules::where('dest_country_id', $destCountryId)
            ->whereIn('hs_code_id', $hsCodeIds)->first();

        $vat = VatSettings::where('country_id', $destCountryId)->first();

        $dutyRate = $rule->duty_rate ?? 0;
        $vatRate = $vat->vat_rate ?? 0;
        $dutyFreeThreshold = (float) ($vat->duty_free_threshold ?? 0);
        $vatFreeThreshold = (float) ($vat->vat_free_threshold ?? 0);

        $duty = ($declaredValue >= $dutyFreeThreshold) ? round($declaredValue * (float) $dutyRate / 100, 2) : 0;
        $vat = (($declaredValue + $duty) >= $vatFreeThreshold) ? round(($declaredValue + $duty) * $vatRate / 100, 2) : 0;

        return ApiResponse::success([
            'duty_rate' => (float) $dutyRate,
            'vat_rate' => $vatRate,
            'estimated_duty' => $duty,
            'estimated_vat' => $vat,
            'estimated_total' => $duty + $vat,
            'is_estimate' => true,
            'disclaimer' => '仅供参考，实际以海关核定为准',
        ]);
    }
}
