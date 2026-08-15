<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 业务种子数据执行器（幂等，可重复执行）
 *
 * 背景：install.sql 仅含表结构与系统表种子（wa_options/wa_roles），
 * countries/payment_gateways/shipping_zones/hs_codes/tariff_rules 等业务表
 * 全新安装后全空，导致商品列表、支付方式、运费/关税计算等主链路开箱不可用
 * （docs/PLAN-RESEARCH.md §1 差距3）。
 *
 * 本脚本补齐最小可用种子：
 *   1. 国家（复用 countries.php 数据源）
 *   2. 物流商（DHL/UPS/EMS）
 *   3. 物流分区 + 分区费率
 *   4. 支付网关（stripe/paypal）+ 支付方式（card/paypal）
 *   5. HS Code 示例库（常见品类）
 *   6. 关税规则示例（US/GB/JP）
 *
 * 幂等策略：全部按业务唯一键查重，已存在则跳过。
 *
 * 用法（service 目录下）：
 *   php database/seeders/run.php
 *
 * 集成：安装向导（admin InstallController）可在导入 install.sql 后调用本脚本，
 * 或由运维在首次启动时手动执行。
 */

require __DIR__ . '/../../vendor/autoload.php';
if (class_exists('Dotenv\Dotenv') && is_file(__DIR__ . '/../../.env')) {
    Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/../..')->safeLoad();
}
require __DIR__ . '/../../support/bootstrap.php';

use app\model\Countries;
use app\model\LogisticsCompanies;
use app\model\ShippingZones;
use app\model\ShippingZoneRates;
use app\model\PaymentGateways;
use app\model\PaymentGatewayMethods;
use app\model\HsCodes;
use app\model\TariffRules;

$inserted = 0;
$skipped = 0;

function seedCount(string $label, callable $fn): void
{
    global $inserted, $skipped;
    $before = $inserted + $skipped;
    $fn($inserted, $skipped);
    $delta = $inserted - $before;
    echo "  [{$label}] new={$delta} (total inserted={$inserted}, skipped={$skipped})\n";
}

echo "=== Erik Shop 业务种子导入 ===\n";

// 1. 国家
seedCount('countries', function (&$ins, &$skip) {
    $data = require __DIR__ . '/countries.php';
    foreach ($data['countries'] as $row) {
        if (Countries::where('iso_code_2', $row['iso_code_2'])->exists()) { $skip++; continue; }
        Countries::create($row + ['status' => 1]);
        $ins++;
    }
});

// 2. 物流商
seedCount('logistics_companies', function (&$ins, &$skip) {
    $items = [
        ['code' => 'dhl', 'name' => 'DHL', 'tracking_url' => 'https://www.dhl.com/cn/zh/home/tracking.html?tracking-id={tracking_no}', 'estimated_days' => '5-10'],
        ['code' => 'ups', 'name' => 'UPS', 'tracking_url' => 'https://www.ups.com/track?tracknum={tracking_no}', 'estimated_days' => '4-9'],
        ['code' => 'ems', 'name' => 'EMS', 'tracking_url' => 'http://www.ems.com.cn/english-main.jsp', 'estimated_days' => '10-20'],
    ];
    foreach ($items as $row) {
        if (LogisticsCompanies::where('code', $row['code'])->exists()) { $skip++; continue; }
        LogisticsCompanies::create($row + ['status' => 1]);
        $ins++;
    }
});

// 3. 物流分区 + 费率
seedCount('shipping_zones', function (&$ins, &$skip) {
    $zones = [
        ['name' => '北美区', 'countries' => ['US', 'CA', 'MX']],
        ['name' => '西欧区', 'countries' => ['GB', 'DE', 'FR', 'NL', 'BE', 'SE', 'DK', 'ES', 'IT']],
        ['name' => '亚太区', 'countries' => ['JP', 'KR', 'AU', 'NZ', 'HK', 'TW', 'SG', 'MY']],
    ];
    foreach ($zones as $row) {
        if (ShippingZones::where('name', $row['name'])->exists()) { $skip++; continue; }
        ShippingZones::create(['name' => $row['name'], 'countries' => json_encode($row['countries']), 'status' => 1]);
        $ins++;
    }
});

seedCount('shipping_zone_rates', function (&$ins, &$skip) {
    $logistics = LogisticsCompanies::where('code', 'dhl')->first();
    if (!$logistics) { echo "    (跳过: 无 DHL 物流商)\n"; return; }
    $zones = ShippingZones::all();
    foreach ($zones as $zone) {
        $exists = ShippingZoneRates::where('zone_id', $zone->id)->where('logistics_id', $logistics->id)->exists();
        if ($exists) { $skip++; continue; }
        ShippingZoneRates::create([
            'zone_id' => $zone->id,
            'logistics_id' => $logistics->id,
            'weight_from' => 0,
            'weight_to' => 2,
            'price' => 9.99,
            'per_kg_price' => 4.50,
        ]);
        $ins++;
    }
});

// 4. 支付网关 + 方式
seedCount('payment_gateways', function (&$ins, &$skip) {
    $items = [
        ['code' => 'stripe', 'name' => 'Stripe'],
        ['code' => 'paypal', 'name' => 'PayPal'],
    ];
    foreach ($items as $row) {
        if (PaymentGateways::where('code', $row['code'])->exists()) { $skip++; continue; }
        PaymentGateways::create($row + ['mode' => 'sandbox', 'status' => 1]);
        $ins++;
    }
});

seedCount('payment_gateway_methods', function (&$ins, &$skip) {
    $stripe = PaymentGateways::where('code', 'stripe')->first();
    $paypal = PaymentGateways::where('code', 'paypal')->first();
    $commonCountries = json_encode(['US', 'CA', 'GB', 'DE', 'FR', 'NL', 'BE', 'SE', 'DK', 'ES', 'IT', 'JP', 'AU', 'SG', 'HK', 'MX']);
    $commonCurrencies = json_encode(['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD', 'SGD', 'HKD', 'SEK', 'DKK', 'MXN']);

    $items = [];
    if ($stripe) {
        $items[] = ['gateway_id' => $stripe->id, 'method_code' => 'card', 'countries' => $commonCountries, 'currencies' => $commonCurrencies];
    }
    if ($paypal) {
        $items[] = ['gateway_id' => $paypal->id, 'method_code' => 'paypal', 'countries' => $commonCountries, 'currencies' => $commonCurrencies];
    }
    foreach ($items as $row) {
        $exists = PaymentGatewayMethods::where('gateway_id', $row['gateway_id'])->where('method_code', $row['method_code'])->exists();
        if ($exists) { $skip++; continue; }
        PaymentGatewayMethods::create($row + ['status' => 1]);
        $ins++;
    }
});

// 5. HS Code 示例库
seedCount('hs_codes', function (&$ins, &$skip) {
    $items = [
        ['code' => '610910', 'description' => 'T-shirts, knitted or crocheted (棉质针织T恤)', 'parent_code' => '6109', 'section' => 'XI Textiles'],
        ['code' => '851712', 'description' => 'Smartphones (智能手机)', 'parent_code' => '8517', 'section' => 'XVI Machinery'],
        ['code' => '847130', 'description' => 'Portable computers ≤10kg (便携电脑)', 'parent_code' => '8471', 'section' => 'XVI Machinery'],
        ['code' => '950300', 'description' => 'Toys (玩具)', 'parent_code' => '9503', 'section' => 'XX Miscellaneous'],
        ['code' => '420222', 'description' => 'Handbags (手提包)', 'parent_code' => '4202', 'section' => 'VIII Leather'],
        ['code' => '640399', 'description' => 'Leather footwear (皮革鞋)', 'parent_code' => '6403', 'section' => 'XII Footwear'],
        ['code' => '851821', 'description' => 'Loudspeakers (扬声器)', 'parent_code' => '8518', 'section' => 'XVI Machinery'],
        ['code' => '910211', 'description' => 'Wrist-watches, mechanical (机械手表)', 'parent_code' => '9102', 'section' => 'XVIII Instruments'],
    ];
    foreach ($items as $row) {
        if (HsCodes::where('code', $row['code'])->exists()) { $skip++; continue; }
        HsCodes::create($row);
        $ins++;
    }
});

// 6. 关税规则示例（US/GB/JP 为目的国）
seedCount('tariff_rules', function (&$ins, &$skip) {
    $rules = [
        // dest_iso, hs_code, duty%, vat%, duty_free_threshold, vat_free_threshold
        ['US', '610910', 16.500, 0.00, 800.00, 0.00],
        ['US', '851712', 0.000, 0.00, 800.00, 0.00],
        ['US', '950300', 0.000, 0.00, 800.00, 0.00],
        ['GB', '610910', 12.000, 20.00, 135.00, 135.00],
        ['GB', '851712', 0.000, 20.00, 135.00, 135.00],
        ['JP', '610910', 9.100, 10.00, 10000.00, 10000.00],
        ['JP', '851712', 0.000, 10.00, 10000.00, 10000.00],
    ];
    foreach ($rules as [$iso, $code, $duty, $vat, $dutyFree, $vatFree]) {
        $country = Countries::where('iso_code_2', $iso)->first();
        $hs = HsCodes::where('code', $code)->first();
        if (!$country || !$hs) { $skip++; continue; }
        $exists = TariffRules::where('dest_country_id', $country->id)->where('hs_code_id', $hs->id)->exists();
        if ($exists) { $skip++; continue; }
        TariffRules::create([
            'dest_country_id' => $country->id,
            'hs_code_id' => $hs->id,
            'duty_rate' => $duty,
            'vat_rate' => $vat,
            'duty_free_threshold' => $dutyFree,
            'vat_free_threshold' => $vatFree,
        ]);
        $ins++;
    }
});

echo "=== 完成：新增 {$inserted} 条，跳过 {$skipped} 条 ===\n";
