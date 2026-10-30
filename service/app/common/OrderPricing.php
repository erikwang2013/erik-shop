<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

use app\model\Countries;
use app\model\ProductHsCodes;
use app\model\ShippingZoneRates;
use app\model\ShippingZones;
use app\model\TariffRules;
use app\model\VatSettings;

/**
 * 订单运费/税费计算（下单与订阅周期购共用）
 */
class OrderPricing
{
    /**
     * 运费计算：目的国 → 物流分区 → 费率阶梯（取最低价物流商），返回金额字符串
     */
    public static function shipping(Countries $country, int $weightGrams): string
    {
        $zone = ShippingZones::where('status', 1)
            ->whereJsonContains('countries', $country->iso_code_2)
            ->first();
        if (!$zone) {
            return '0.00'; // 无分区规则时不收运费（订单不阻断，运费后续可补）
        }
        $weightKg = $weightGrams / 1000; // SQL 阶梯区间匹配用，保持数值型
        $rates = ShippingZoneRates::where('zone_id', $zone->id)
            ->where('weight_from', '<=', $weightKg)
            ->where(function ($q) use ($weightKg) {
                $q->where('weight_to', '>=', $weightKg)->orWhereNull('weight_to');
            })
            ->get();
        $minFee = null;
        foreach ($rates as $rate) {
            // 费用 = 起步价 + (克/1000) × 每公斤价；克→公斤用 Money::div 精确
            $fee = Money::add((string) $rate->price, Money::mul(Money::div((string) $weightGrams, '1000'), (string) $rate->per_kg_price));
            $minFee = ($minFee === null) ? $fee : (Money::cmp($minFee, $fee) <= 0 ? $minFee : $fee);
        }
        return $minFee === null ? '0.00' : Money::round($minFee);
    }

    /**
     * 关税/VAT 估算：商品 HS Code → 目的国税率（无 HS 关联或税率规则则按 0），返回金额字符串
     *
     * 免税阈值按整单/整包裹判断：duty 阈值用整单申报价值判断一次；
     * VAT 基数 = 整单申报价值 + 关税总额，VAT 阈值用该基数判断一次。
     *
     * @param array $productValues product_id => 十进制金额字符串
     */
    public static function tax(Countries $country, array $productValues): string
    {
        $destCountryId = $country->id;
        $vat = VatSettings::where('country_id', $destCountryId)->first();
        $vatRate = (string) ($vat->vat_rate ?? 0);
        $dutyFreeThreshold = (string) ($vat->duty_free_threshold ?? 0);
        $vatFreeThreshold = (string) ($vat->vat_free_threshold ?? 0);

        $consignmentValue = '0';
        foreach ($productValues as $value) {
            $consignmentValue = Money::add($consignmentValue, (string) $value);
        }

        $dutyTotal = '0';
        if (Money::cmp($consignmentValue, $dutyFreeThreshold) >= 0) {
            foreach ($productValues as $productId => $value) {
                $hsCodeIds = ProductHsCodes::where('product_id', $productId)->pluck('hs_code_id');
                if ($hsCodeIds->isEmpty()) {
                    continue;
                }
                $rule = TariffRules::where('dest_country_id', $destCountryId)
                    ->whereIn('hs_code_id', $hsCodeIds)
                    ->first();
                $dutyRate = (string) ($rule->duty_rate ?? 0);
                $dutyTotal = Money::add($dutyTotal, Money::round(Money::div(Money::mul((string) $value, $dutyRate), '100')));
            }
            $dutyTotal = Money::round($dutyTotal);
        }

        $vatBase = Money::add($consignmentValue, $dutyTotal);
        $vatAmount = Money::cmp($vatBase, $vatFreeThreshold) >= 0
            ? Money::round(Money::div(Money::mul($vatBase, $vatRate), '100'))
            : '0.00';

        return Money::round(Money::add($dutyTotal, $vatAmount));
    }
}
