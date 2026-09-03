<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;
use app\common\ApiResponse;
use app\common\Money;
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
        if (empty($productId) || empty($destCountryId)) {
            return ApiResponse::fail('缺少商品或目的国参数', 422);
        }
        // 申报价值入参归一：Money::normalize 归一分位字符串（E 类）
        $declaredValue = Money::normalize($request->input('declared_value', 0));

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
        $dutyFreeThreshold = (string) ($vat->duty_free_threshold ?? 0);
        $vatFreeThreshold = (string) ($vat->vat_free_threshold ?? 0);

        // 免税阈值判定与税额计算均十进制；税率按 % 处理：×率÷100，末位一次 round(2)
        $duty = Money::cmp($declaredValue, $dutyFreeThreshold) >= 0
            ? Money::round(Money::div(Money::mul($declaredValue, (string) $dutyRate), '100'))
            : '0.00';
        $vatBase = Money::add($declaredValue, $duty);
        $vat = Money::cmp($vatBase, $vatFreeThreshold) >= 0
            ? Money::round(Money::div(Money::mul($vatBase, (string) $vatRate), '100'))
            : '0.00';
        $estimatedTotal = Money::round(Money::add($duty, $vat));

        // JSON 输出边界 (float) 展示转换（估算值不再参与运算）
        return ApiResponse::success([
            'duty_rate' => (float) $dutyRate,
            'vat_rate' => (float) $vatRate,
            'estimated_duty' => (float) $duty,
            'estimated_vat' => (float) $vat,
            'estimated_total' => (float) $estimatedTotal,
            'is_estimate' => true,
            'disclaimer' => '仅供参考，实际以海关核定为准',
        ]);
    }
}
