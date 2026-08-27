<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * 公开接口测试：健康检查/商品/分类/内容/搜索/营销/公共服务
 */

// ===== 健康检查 =====
check('健康检查', 'GET', '/health', ['expect' => 200, 'expect_key' => 'status']);

// ===== 国家/币种 =====
check('国家列表', 'GET', '/api/countries', ['expect' => 200, 'expect_contains' => 'United States']);
check('国家列表含币种', 'GET', '/api/countries', ['expect' => 200, 'expect_contains' => 'JPY']);

// ===== 商品列表/详情 =====
check('商品列表', 'GET', '/api/products', ['expect' => 200, 'expect_contains' => 'Test Cotton Dress']);
check('商品列表-分页参数', 'GET', '/api/products?page=1&per_page=5', ['expect' => 200, 'expect_key' => 'list']);
check('商品列表-分类筛选', 'GET', '/api/products?category_id=' . enc(1), ['expect' => 200, 'expect_contains' => 'Test Cotton Dress']);
check('商品列表-关键词', 'GET', '/api/products?keyword=sneaker', ['expect' => 200, 'expect_contains' => 'Test Sneakers']);
check('商品列表-排序price_asc', 'GET', '/api/products?sort=price_asc', ['expect' => 200, 'expect_key' => 'list']);
check('商品列表-价格区间', 'GET', '/api/products?min_price=10&max_price=40', ['expect' => 200, 'expect_key' => 'list']);
check('商品详情', 'GET', '/api/products/' . enc(1001), ['expect' => 200, 'expect_contains' => 'Test Cotton Dress']);
check('商品详情-多币种EUR', 'GET', '/api/products/' . enc(1001) . '?currency=EUR', ['expect' => 200, 'expect_contains' => 'EUR']);
check('商品详情-多语言ja', 'GET', '/api/products/' . enc(1001), ['headers' => ['Accept-Language: ja'], 'expect' => 200, 'expect_contains' => 'テストコットンワンピース']);
check('商品详情-含SKU', 'GET', '/api/products/' . enc(1001), ['expect' => 200, 'expect_key' => 'skus']);
check('商品详情-草稿404', 'GET', '/api/products/' . enc(1003), ['expect' => 404]);
check('商品详情-非法ID', 'GET', '/api/products/not-a-hashid', ['expect' => 404]);

// ===== 分类 =====
check('分类列表', 'GET', '/api/categories', ['expect' => 200, 'expect_contains' => 'Clothing']);
check('分类列表-子分类', 'GET', '/api/categories?parent_id=1', ['expect' => 200, 'expect_contains' => 'Dresses']);
check('分类树', 'GET', '/api/categories/tree', ['expect' => 200, 'expect_contains' => 'Clothing']);

// ===== 内容 =====
check('轮播图-home', 'GET', '/api/banners?position=home', ['expect' => 200, 'expect_contains' => 'Summer Sale']);
check('轮播图-category', 'GET', '/api/banners?position=category', ['expect' => 200, 'expect_contains' => 'Category Banner']);
check('公开配置-general', 'GET', '/api/settings?group=general', ['expect' => 200, 'expect_contains' => 'GlobalShop']);
check('公开配置-默认分组', 'GET', '/api/settings', ['expect' => 200]);
check('FAQ-shipping', 'GET', '/api/faq?category=shipping', ['expect' => 200, 'expect_contains' => 'How long does shipping take?']);
check('FAQ-多语言ja', 'GET', '/api/faq?category=shipping', ['headers' => ['Accept-Language: ja'], 'expect' => 200, 'expect_contains' => '配送には']);
check('CMS页面', 'GET', '/api/cms/about-us', ['expect' => 200, 'expect_contains' => 'About Us']);
check('CMS-多语言zh_CN', 'GET', '/api/cms/about-us', ['headers' => ['Accept-Language: zh_CN'], 'expect' => 200, 'expect_contains' => '关于我们']);
check('CMS-不存在404', 'GET', '/api/cms/no-such-page', ['expect' => 404]);

// ===== 搜索（ES 不可用时降级 DB，均返回200） =====
check('搜索-关键词', 'GET', '/api/search?keyword=dress', ['expect' => 200, 'expect_key' => 'list']);
check('搜索-多语言ja', 'GET', '/api/search?keyword=ワンピース', ['headers' => ['Accept-Language: ja'], 'expect' => 200, 'expect_key' => 'list']);
check('搜索-分类筛选', 'GET', '/api/search?keyword=dress&category_id=' . enc(1), ['expect' => 200, 'expect_key' => 'list']);
check('搜索-空关键词422', 'GET', '/api/search', ['expect' => 422]);
check('搜索-超长关键词422', 'GET', '/api/search?keyword=' . str_repeat('a', 101), ['expect' => 422]);

// ===== 评价（公开列表） =====
check('评价列表', 'GET', '/api/reviews/' . enc(1001), ['expect' => 200, 'expect_contains' => 'Great dress']);
check('评价列表-评分筛选', 'GET', '/api/reviews/' . enc(1001) . '?rating=5', ['expect' => 200, 'expect_contains' => 'Great dress']);
check('评价列表-分页', 'GET', '/api/reviews/' . enc(1001) . '?page=1&per_page=5', ['expect' => 200, 'expect_key' => 'list']);

// ===== 营销 =====
check('秒杀列表', 'GET', '/api/flash-sales', ['expect' => 200, 'expect_contains' => '限时秒杀']);
check('秒杀列表-进行中', 'GET', '/api/flash-sales', ['expect' => 200, 'expect_contains' => '19.99']);
check('拼团列表', 'GET', '/api/group-buys', ['expect' => 200, 'expect_contains' => '2人成团']);

// ===== 公共服务 =====
check('尺码表', 'GET', '/api/size-charts?category_id=1&type=clothing', ['expect' => 200, 'expect_contains' => 'Dress Size Chart']);
check('尺码表-无参数', 'GET', '/api/size-charts', ['expect' => 200]);
check('关税估算-GB', 'GET', '/api/tariff/estimate?product_id=' . enc(1001) . '&dest_country_id=3&declared_value=100', ['expect' => 200, 'expect_key' => 'estimated_total']);
check('关税估算-US免税', 'GET', '/api/tariff/estimate?product_id=' . enc(1001) . '&dest_country_id=1&declared_value=100', ['expect' => 200, 'expect_key' => 'estimated_total']);
check('关税估算-缺商品', 'GET', '/api/tariff/estimate?dest_country_id=3&declared_value=100', ['expect_any' => [404, 422]]);
check('运费计算-US', 'GET', '/api/shipping/calculate?dest_country_id=1&weight=500', ['expect' => 200, 'expect_key' => 'options']);
check('运费计算-默认重量', 'GET', '/api/shipping/calculate?dest_country_id=1', ['expect' => 200, 'expect_key' => 'options']);
check('运费计算-国家不存在404', 'GET', '/api/shipping/calculate?dest_country_id=999', ['expect' => 404]);
check('支付方式-US', 'GET', '/api/payment/methods?country=US&currency=USD', ['expect' => 200, 'expect_contains' => 'stripe']);
// PaymentController 有意过滤未实现的 Klarna/Adyen 网关（仅暴露 stripe/paypal），DE/EUR 下断言已实现网关
check('支付方式-DE返回已实现网关', 'GET', '/api/payment/methods?country=DE&currency=EUR', ['expect' => 200, 'expect_contains' => 'paypal']);
check('支付方式-默认参数', 'GET', '/api/payment/methods', ['expect' => 200, 'expect_contains' => 'card']);
check('GeoIP检测', 'GET', '/api/geoip/detect', ['expect' => 200]);
check('合规检查', 'GET', '/api/compliance/check?product_id=' . enc(1001) . '&dest_country_id=1', ['expect' => 200]);
check('合规检查-缺参', 'GET', '/api/compliance/check', ['expect_any' => [400, 404, 422]]);
