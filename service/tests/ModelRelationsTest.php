<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use app\model\Categories;
use app\model\OrderItems;
use app\model\OrderLogs;
use app\model\Orders;
use app\model\ProductImages;
use app\model\ProductReviews;
use app\model\ProductSkuPrices;
use app\model\ProductSkus;
use app\model\Products;
use app\model\ProductTranslations;
use app\model\UserAddresses;

/**
 * 核心模型关联关系集成测试
 *
 * 造最小数据后验证 Eloquent 关联方法返回正确的目标模型与过滤条件
 * （hasMany 按外键、belongsTo 按主键、链式 skus.prices 等），
 * 覆盖商品/分类/订单/用户四大领域。
 */
class ModelRelationsTest extends IntegrationTestCase
{
    use TestSeederTrait;

    private int $productId;
    private int $skuId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDb();
        // 商品 + SKU + USD 价格 + 翻译 + 图片
        $product = Products::create(['title' => 'Rel Product', 'status' => 2, 'main_image' => 'rel.jpg']);
        $this->productId = (int) $product->id;
        $sku = ProductSkus::create([
            'product_id' => $product->id, 'sku_code' => 'SKU' . uniqid(),
            'default_price' => 9.9, 'stock' => 5, 'status' => 1,
        ]);
        $this->skuId = (int) $sku->id;
        ProductSkuPrices::create(['sku_id' => $sku->id, 'currency_code' => 'USD', 'price' => 9.9]);
        ProductTranslations::create([
            'product_id' => $product->id, 'locale' => 'en', 'title' => 'Rel EN', 'description' => 'desc',
        ]);
        ProductImages::create(['product_id' => $product->id, 'url' => 'rel-1.jpg', 'sort' => 1]);
        $this->trackCreated('erik_products', $this->productId);
        $this->trackCreated('erik_product_skus', $this->skuId);
        $this->trackCreated('erik_product_sku_prices', (int) ProductSkuPrices::where('sku_id', $sku->id)->value('id'));
        $this->trackCreated('erik_product_translations', (int) ProductTranslations::where('product_id', $product->id)->value('id'));
        $this->trackCreated('erik_product_images', (int) ProductImages::where('product_id', $product->id)->value('id'));
    }

    public function test_product_relations_resolve(): void
    {
        $product = Products::with(['skus', 'translation', 'images'])->find($this->productId);

        $this->assertCount(1, $product->skus);
        $this->assertInstanceOf(ProductSkus::class, $product->skus->first());
        $this->assertSame($this->skuId, (int) $product->skus->first()->id);

        $this->assertCount(1, $product->translation);
        $this->assertInstanceOf(ProductTranslations::class, $product->translation->first());
        $this->assertSame('Rel EN', $product->translation->first()->title);

        $this->assertCount(1, $product->images);
        $this->assertInstanceOf(ProductImages::class, $product->images->first());
    }

    public function test_sku_relations_chain(): void
    {
        $sku = ProductSkus::with(['product', 'prices'])->find($this->skuId);

        $this->assertInstanceOf(Products::class, $sku->product);
        $this->assertSame($this->productId, (int) $sku->product->id);

        $this->assertCount(1, $sku->prices);
        $this->assertInstanceOf(ProductSkuPrices::class, $sku->prices->first());
        $this->assertSame('USD', $sku->prices->first()->currency_code);
    }

    public function test_product_reviews_relation_empty_and_seeded(): void
    {
        $this->assertCount(0, Products::find($this->productId)->reviews);

        ProductReviews::create([
            'user_id' => 1, 'product_id' => $this->productId,
            'order_id' => 0, 'sku_id' => 0, 'rating' => 5, 'content' => 'good', 'status' => 1,
        ]);
        $reviews = Products::find($this->productId)->reviews;
        $this->assertCount(1, $reviews);
        $this->assertInstanceOf(ProductReviews::class, $reviews->first());
        $this->assertSame(5, (int) $reviews->first()->rating);
    }

    public function test_order_relations_resolve(): void
    {
        $order = Orders::create([
            'order_no' => 'ORD' . date('Ymd') . uniqid(),
            'user_id' => 1, 'status' => 0, 'currency_code' => 'USD',
        ]);
        $this->trackCreated('erik_orders', (int) $order->id);
        OrderItems::create([
            'order_id' => $order->id, 'product_id' => $this->productId, 'sku_id' => $this->skuId,
            'title' => 'Rel Product', 'price' => 9.9, 'quantity' => 1, 'subtotal' => 9.9,
        ]);
        OrderLogs::create([
            'order_id' => $order->id, 'to_status' => 0, 'operator' => 'user', 'remark' => '创建订单',
        ]);

        $fresh = Orders::with(['items', 'logs', 'user'])->find($order->id);
        $this->assertCount(1, $fresh->items);
        $this->assertInstanceOf(OrderItems::class, $fresh->items->first());
        $this->assertSame($this->skuId, (int) $fresh->items->first()->sku_id);
        $this->assertCount(1, $fresh->logs);
        $this->assertInstanceOf(OrderLogs::class, $fresh->logs->first());
    }

    public function test_category_tree_build(): void
    {
        $parent = Categories::create(['name' => 'Parent', 'slug' => 'p-' . uniqid(), 'parent_id' => 0, 'level' => 1, 'sort' => 1, 'status' => 1]);
        $child = Categories::create(['name' => 'Child', 'slug' => 'c-' . uniqid(), 'parent_id' => $parent->id, 'level' => 2, 'sort' => 1, 'status' => 1]);
        $this->trackCreated('erik_categories', (int) $parent->id);
        $this->trackCreated('erik_categories', (int) $child->id);

        $tree = Categories::where('status', 1)->orderBy('sort')->get()->toArray();
        $this->assertCount(2, $tree);

        // 父子层级查询：ProductController::index 的分类筛选取子分类
        $childIds = Categories::where('parent_id', $parent->id)->pluck('id')->toArray();
        $this->assertContains((int) $child->id, array_map('intval', $childIds));
    }

    public function test_user_address_relation(): void
    {
        $userId = $this->seedUser();
        UserAddresses::create([
            'user_id' => $userId, 'name' => 'QA', 'phone' => '13800000000',
            'country_id' => 1, 'detail' => 'Addr 1', 'is_default' => 1,
        ]);

        $user = \app\model\Users::find($userId);
        $this->assertCount(1, $user->userAddresses);
        $this->assertInstanceOf(UserAddresses::class, $user->userAddresses->first());
        $this->assertSame(1, (int) $user->userAddresses->first()->is_default);
    }
}
