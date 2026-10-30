<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests\Integration;

use app\common\OrderPricing;
use app\model\Countries;
use app\model\HsCodes;
use app\model\ProductHsCodes;
use app\model\Products;
use app\model\TariffRules;
use app\model\VatSettings;
use PHPUnit\Framework\Attributes\Test;
use support\Db;
use tests\IntegrationTestCase;

/**
 * 订单税费计算回归测试：免税阈值按整单判断、VAT 基数含关税
 */
class OrderPricingTest extends IntegrationTestCase
{
    /** @var array<string, int[]> table => ids */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDb();
    }

    protected function tearDown(): void
    {
        if (self::$dbAvailable) {
            foreach ($this->created as $table => $ids) {
                if ($ids) {
                    Db::table($table)->whereIn('id', $ids)->delete();
                }
            }
        }
        parent::tearDown();
    }

    private function track(string $table, int $id): void
    {
        $this->created[$table][] = $id;
    }

    private function seedCountry(): Countries
    {
        // 固定使用未分配的 QQ，避免随机两字母撞上种子国家的 uk_iso_code_2
        Countries::where('iso_code_2', 'QQ')->forceDelete();
        $country = Countries::create([
            'name_en' => 'QA Tax ' . uniqid(),
            'name_cn' => '测试国家',
            'iso_code_2' => 'QQ',
            'iso_code_3' => 'QQQ',
            'currency_code' => 'USD',
            'status' => 1,
        ]);
        $this->track('shop_countries', (int) $country->id);
        return $country;
    }

    private function seedVat(Countries $country, string $vatRate, string $dutyFree, string $vatFree): void
    {
        $vat = VatSettings::create([
            'country_id' => $country->id,
            'vat_rate' => $vatRate,
            'duty_free_threshold' => $dutyFree,
            'vat_free_threshold' => $vatFree,
        ]);
        $this->track('shop_vat_settings', (int) $vat->id);
    }

    /** 造商品 + HS Code + 目的国关税规则，返回 product_id */
    private function seedProductWithHs(Countries $country, string $dutyRate): int
    {
        // CHAR(6) 且 uk_code；随机 6 位可能撞种子数据，先查重
        do {
            $hsCode = sprintf('%06d', random_int(0, 999999));
        } while (HsCodes::where('code', $hsCode)->exists());

        $hs = HsCodes::create([
            'code' => $hsCode,
            'description' => 'QA HS ' . uniqid(),
        ]);
        $this->track('shop_hs_codes', (int) $hs->id);

        $rule = TariffRules::create([
            'dest_country_id' => $country->id,
            'hs_code_id' => $hs->id,
            'duty_rate' => $dutyRate,
        ]);
        $this->track('shop_tariff_rules', (int) $rule->id);

        $product = Products::create(['title' => 'QA Tax Product ' . uniqid(), 'status' => 2]);
        $this->track('shop_products', (int) $product->id);

        $link = ProductHsCodes::create([
            'product_id' => $product->id,
            'hs_code_id' => $hs->id,
            'is_primary' => 1,
        ]);
        $this->track('shop_product_hs_codes', (int) $link->id);

        return (int) $product->id;
    }

    private function seedProductWithoutHs(): int
    {
        $product = Products::create(['title' => 'QA No HS ' . uniqid(), 'status' => 2]);
        $this->track('shop_products', (int) $product->id);
        return (int) $product->id;
    }

    #[Test]
    public function consignment_over_threshold_charges_duty_per_item_and_vat_on_duty_inclusive_base(): void
    {
        $country = $this->seedCountry();
        $this->seedVat($country, '20.00', '100.00', '0.00');
        $productA = $this->seedProductWithHs($country, '10.000');
        $productB = $this->seedProductWithHs($country, '10.000');

        // 单件 60 低于 100 阈值，但整单 120 已超 → 两件都按 10% 征关税
        $tax = OrderPricing::tax($country, [$productA => '60.00', $productB => '60.00']);

        // duty = 6.00 + 6.00 = 12.00；VAT 基数 = 120 + 12 = 132 → 26.40；合计 38.40
        $this->assertSame('38.40', $tax);
    }

    #[Test]
    public function consignment_below_thresholds_pays_no_duty_and_no_vat(): void
    {
        $country = $this->seedCountry();
        $this->seedVat($country, '20.00', '100.00', '50.00');
        $product = $this->seedProductWithHs($country, '10.000');

        // 40 < 关税阈值 100 → 无关税；40 < VAT 阈值 50 → 无 VAT
        $this->assertSame('0.00', OrderPricing::tax($country, [$product => '40.00']));
        // 60 ≥ VAT 阈值 50（但仍 < 关税阈值）→ VAT = 60 * 20% = 12.00
        $this->assertSame('12.00', OrderPricing::tax($country, [$product => '60.00']));
    }

    #[Test]
    public function duty_threshold_is_inclusive(): void
    {
        $country = $this->seedCountry();
        $this->seedVat($country, '0.00', '100.00', '0.00');
        $product = $this->seedProductWithHs($country, '10.000');

        $this->assertSame('0.00', OrderPricing::tax($country, [$product => '99.99']));
        // 恰好等于阈值也征税（>=）
        $this->assertSame('10.00', OrderPricing::tax($country, [$product => '100.00']));
    }

    #[Test]
    public function product_without_hs_code_contributes_to_consignment_but_not_duty(): void
    {
        $country = $this->seedCountry();
        $this->seedVat($country, '20.00', '0.00', '0.00');
        $withHs = $this->seedProductWithHs($country, '10.000');
        $withoutHs = $this->seedProductWithoutHs();

        // 关税仅来自有 HS 关联的商品：50 * 10% = 5.00；VAT 基数 = 100 + 5 = 105 → 21.00
        $this->assertSame('26.00', OrderPricing::tax($country, [$withHs => '50.00', $withoutHs => '50.00']));
    }

    #[Test]
    public function missing_vat_settings_defaults_to_zero_tax(): void
    {
        $country = $this->seedCountry();
        $product = $this->seedProductWithoutHs();

        $this->assertSame('0.00', OrderPricing::tax($country, [$product => '100.00']));
    }
}
