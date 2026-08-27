<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use app\model\Countries;
use app\model\ProductSkuPrices;
use app\model\ProductSkus;
use app\model\Products;
use app\model\Users;
use support\Db;

/**
 * 集成测试公共造数工具
 *
 * 与现有集成测试风格一致：每用例前清库（IntegrationTestCase），
 * 用例内造数据统一通过本 Trait 生成并登记，tearDown 时按登记删除，
 * 显式指定 DB_NAME（不重置）时也能干净退出。
 */
trait TestSeederTrait
{
    /** @var array<string, int[]> table => ids */
    private array $seeded = [];

    protected function trackCreated(string $table, int $id): void
    {
        $this->seeded[$table][] = $id;
    }

    /**
     * 按登记顺序删除用例内造的数据（先删子表后删主表由登记顺序保证）
     */
    protected function cleanupCreated(): void
    {
        if (!self::$dbAvailable) {
            return;
        }
        foreach ($this->seeded as $table => $ids) {
            if ($ids) {
                Db::table($table)->whereIn('id', $ids)->delete();
            }
        }
        $this->seeded = [];
    }

    /**
     * 造用户（uk_invite_code / uk_email_hash 唯一，用随机值）
     */
    protected function seedUser(array $extra = []): int
    {
        $user = Users::create($extra + [
            'invite_code' => 'T' . substr(md5(uniqid('', true)), 0, 8),
            'email' => 'qa_' . uniqid() . '@example.com',
            'email_hash' => Users::emailHash('qa_' . uniqid() . '@example.com'),
            'nickname' => 'QA Tester',
            'status' => 1,
        ]);
        $this->trackCreated('shop_users', (int) $user->id);
        return (int) $user->id;
    }

    /**
     * 造国家（uk_iso_code_2 唯一，用随机大写字母）
     */
    protected function seedCountry(string $currency = 'USD'): int
    {
        $country = Countries::create([
            'name_en' => 'Test Country', 'name_cn' => '测试国家',
            'iso_code_2' => chr(65 + random_int(0, 25)) . chr(65 + random_int(0, 25)),
            'iso_code_3' => 'USA',
            'currency_code' => $currency, 'status' => 1, 'kyc_required' => 0,
        ]);
        $this->trackCreated('shop_countries', (int) $country->id);
        return (int) $country->id;
    }

    /**
     * 造商品 + SKU（uk_sku_code 唯一）+ USD 定价，返回 sku_id
     */
    protected function seedSku(float $price = 10.0, int $stock = 10, int $status = 1): int
    {
        $product = Products::create(['title' => 'QA Product', 'status' => 2]);
        $this->trackCreated('shop_products', (int) $product->id);
        $sku = ProductSkus::create([
            'product_id' => $product->id, 'sku_code' => 'SKU' . uniqid(),
            'default_price' => $price, 'stock' => $stock, 'status' => $status,
        ]);
        $this->trackCreated('shop_product_skus', (int) $sku->id);
        $this->trackCreated('shop_product_sku_prices', (int) ProductSkuPrices::create([
            'sku_id' => $sku->id, 'currency_code' => 'USD', 'price' => $price,
        ])->id);
        return (int) $sku->id;
    }

    /**
     * 统一响应解析：调用控制器方法并返回 [statusCode, json数组]
     */
    protected function callController(object $controller, string $method, \Webman\Http\Request $request, array $args = []): array
    {
        $res = $controller->{$method}($request, ...$args);
        $json = json_decode($res->rawBody(), true);
        // 应用惯例：业务错误为 HTTP 200 + body code（422/401 等），返回语义码
        $code = (isset($json['code']) && (int) $json['code'] !== 0) ? (int) $json['code'] : $res->getStatusCode();
        return [$code, $json];
    }

    /**
     * 构造登录态请求（设置 userId + 可选 locale/geoCountry）
     */
    protected function authRequest(string $method, string $uri, array $body = [], int $userId = 1, array $props = []): \Webman\Http\Request
    {
        $req = $this->makeRequest($method, $uri, $body);
        $req->userId = $userId;
        foreach ($props as $k => $v) {
            $req->{$k} = $v;
        }
        return $req;
    }
}
