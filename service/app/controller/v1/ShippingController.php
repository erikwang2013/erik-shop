<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;
use app\common\ApiResponse;
use app\common\Money;
use app\model\ShippingZones;
use app\model\ShippingZoneRates;
use app\model\Countries;
use Webman\Http\Request;

class ShippingController extends \app\controller\BaseApiController
{
    /**
     * 运费计算
     * GET /api/shipping/calculate?dest_country_id=xxx&weight=1500&sku_ids=1,2,3
     */
    public function calculate(Request $request): \support\Response
    {
        $destCountryId = $request->input('dest_country_id');
        $weightGrams = (int) $request->input('weight', 500);
        $country = Countries::find($destCountryId);

        if (!$country) {
            return ApiResponse::fail('目的国不存在', 404);
        }

        $zone = ShippingZones::where('status', 1)
            ->whereJsonContains('countries', $country->iso_code_2)
            ->first();

        if (!$zone) {
            return ApiResponse::fail('暂不支持配送至该国家', 422);
        }

        $weightKg = $weightGrams / 1000;
        $rates = ShippingZoneRates::where('zone_id', $zone->id)
            ->where('weight_from', '<=', $weightKg)
            ->where(function ($q) use ($weightKg) {
                $q->where('weight_to', '>=', $weightKg)->orWhereNull('weight_to');
            })
            ->with('logistics')
            ->get()
            ->map(fn($r) => [
                'logistics_name' => $r->logistics->name ?? '',
                'logistics_code' => $r->logistics->code ?? '',
                // 运费 = 起步价 + (克/1000) × 每公斤价（十进制），JSON 边界 (float) 展示
                'fee' => (float) Money::round(Money::add((string) $r->price, Money::mul(Money::div((string) $weightGrams, '1000'), (string) $r->per_kg_price))),
                'estimated_days' => $r->logistics->estimated_days ?? '7-15',
                'tracking_url' => $r->logistics->tracking_url ?? '',
            ]);

        return ApiResponse::success([
            'zone_name' => $zone->name,
            'weight_kg' => round($weightKg, 3),
            'dest_country' => $country->iso_code_2,
            'options' => $rates,
        ]);
    }
}
