<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * 测试种子数据：countries/currencies/产品/HS关税/物流/支付方式/营销/会员等
 */

/** 表是否存在指定列（按表缓存 information_schema 查询） */
function tableHasColumn(string $table, string $col): bool
{
    static $cache = [];
    if (!isset($cache[$table])) {
        $st = db()->prepare(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?'
        );
        $st->execute([API_DB, $table, $col]);
        $cache[$table] = (int) $st->fetchColumn() > 0;
    }
    return $cache[$table];
}

/** 通用插入助手（供测试文件动态补数，如 affiliate/b2b 关联当前用户） */
function dbInsert(string $table, array $cols, array $rows): void
{
    // Snowflake 主键无自增：插入缺 id 且表有 id 列时自动补生成值
    if (!in_array('id', $cols, true) && tableHasColumn($table, 'id')) {
        $cols[] = 'id';
        static $autoId = 900000;
        foreach ($rows as &$row) {
            $row['id'] = $autoId++;
        }
        unset($row);
    }
    $pdo = db();
    $place = rtrim(str_repeat('?,', count($cols)), ',');
    $sql = "INSERT INTO `{$table}` (`" . implode('`,`', $cols) . "`) VALUES ({$place})";
    $stmt = $pdo->prepare($sql);
    foreach ($rows as $row) {
        $stmt->execute(array_values($row));
    }
}

function seedData(): void
{
    $now = date('Y-m-d H:i:s');
    $db = db();

    dbInsert('shop_countries', ['id', 'name_en', 'name_cn', 'iso_code_2', 'iso_code_3', 'phone_code', 'currency_code', 'flag_emoji', 'timezone', 'price_display_mode', 'kyc_required', 'status', 'sort'], [
        [1, 'United States', '美国', 'US', 'USA', '+1', 'USD', '🇺🇸', 'America/Los_Angeles', 'tax_exclusive', 0, 1, 1],
        [2, 'Canada', '加拿大', 'CA', 'CAN', '+1', 'CAD', '🇨🇦', 'America/Toronto', 'tax_exclusive', 0, 1, 2],
        [3, 'United Kingdom', '英国', 'GB', 'GBR', '+44', 'GBP', '🇬🇧', 'Europe/London', 'tax_inclusive', 0, 1, 3],
        [4, 'Germany', '德国', 'DE', 'DEU', '+49', 'EUR', '🇩🇪', 'Europe/Berlin', 'tax_inclusive', 0, 1, 4],
        [5, 'Japan', '日本', 'JP', 'JPN', '+81', 'JPY', '🇯🇵', 'Asia/Tokyo', 'both', 0, 1, 5],
    ]);
    dbInsert('shop_currencies', ['id', 'code', 'name', 'symbol', 'is_default', 'status'], [
        [1, 'USD', 'US Dollar', '$', 1, 1],
        [2, 'EUR', 'Euro', '€', 0, 1],
        [3, 'GBP', 'British Pound', '£', 0, 1],
        [4, 'JPY', 'Japanese Yen', '¥', 0, 1],
        [5, 'CAD', 'Canadian Dollar', 'C$', 0, 1],
    ]);
    dbInsert('shop_exchange_rates', ['id', 'from_currency', 'to_currency', 'rate', 'source', 'effective_at'], [
        [11, 'USD', 'EUR', 0.92, 'manual', $now],
        [12, 'USD', 'GBP', 0.79, 'manual', $now],
        [13, 'USD', 'JPY', 149.5, 'manual', $now],
        [14, 'USD', 'CAD', 1.36, 'manual', $now],
        [15, 'EUR', 'USD', 1.09, 'manual', $now],
        [16, 'GBP', 'USD', 1.27, 'manual', $now],
        [17, 'JPY', 'USD', 0.0067, 'manual', $now],
        [18, 'CAD', 'USD', 0.735, 'manual', $now],
    ]);

    dbInsert('shop_categories', ['id', 'parent_id', 'name', 'slug', 'sort', 'status', 'is_hot', 'level'], [
        [1, 0, 'Clothing', 'clothing', 1, 1, 1, 1],
        [2, 1, 'Dresses', 'dresses', 1, 1, 0, 2],
        [3, 0, 'Shoes', 'shoes', 2, 1, 1, 1],
    ]);

    dbInsert('shop_products', ['id', 'category_id', 'title', 'subtitle', 'slug', 'brand', 'main_image', 'description', 'status', 'is_hot', 'is_new', 'is_recommend', 'sales_count', 'min_price', 'max_price', 'weight', 'unit'], [
        [1001, 1, 'Test Cotton Dress', 'Comfy summer dress', 'test-cotton-dress', 'TestBrand', 'https://img.example.com/p1.jpg', 'A comfortable cotton dress for summer.', 2, 1, 1, 1, 100, 29.99, 49.99, 500, 'piece'],
        [1002, 3, 'Test Sneakers', 'White casual sneakers', 'test-sneakers', 'TestBrand', 'https://img.example.com/p2.jpg', 'Casual white sneakers.', 2, 0, 1, 0, 50, 59.99, 79.99, 800, 'pair'],
        [1003, 3, 'Draft Product', '', 'draft-product', '', '', '', 0, 0, 0, 0, 0, 9.99, 9.99, 100, 'piece'],
    ]);
    dbInsert('shop_product_translations', ['product_id', 'locale', 'title', 'subtitle', 'description'], [
        [1001, 'en', 'Test Cotton Dress', 'Comfy summer dress', 'A comfortable cotton dress for summer.'],
        [1001, 'ja', 'テストコットンワンピース', '夏のワンピース', '夏にぴったりのコットンワンピース。'],
        [1001, 'zh_CN', '测试棉质连衣裙', '舒适夏装', '适合夏季穿着的舒适棉质连衣裙。'],
        [1002, 'en', 'Test Sneakers', 'White casual sneakers', 'Casual white sneakers.'],
        [1002, 'ja', 'テストスニーカー', '白いカジュアルスニーカー', 'カジュアルな白いスニーカー。'],
    ]);
    dbInsert('shop_product_skus', ['id', 'product_id', 'sku_code', 'attrs', 'default_price', 'origin_price', 'cost_price', 'stock', 'status', 'sales_count'], [
        [2001, 1001, 'SKU-RED-M', '{"color":"Red","size":"M"}', 29.99, 49.99, 10.00, 100, 1, 60],
        [2002, 1001, 'SKU-RED-L', '{"color":"Red","size":"L"}', 39.99, 59.99, 15.00, 50, 1, 20],
        [2003, 1002, 'SKU-WHT-42', '{"color":"White","size":"42"}', 59.99, 89.99, 25.00, 30, 1, 10],
    ]);
    dbInsert('shop_product_sku_prices', ['sku_id', 'currency_code', 'price', 'origin_price'], [
        [2001, 'USD', 29.99, 49.99],
        [2001, 'EUR', 27.50, 46.00],
        [2001, 'JPY', 4499.00, 7499.00],
        [2002, 'USD', 39.99, 59.99],
        [2002, 'JPY', 5999.00, 8999.00],
        [2003, 'USD', 59.99, 89.99],
    ]);
    dbInsert('shop_product_images', ['product_id', 'url', 'sort', 'is_main'], [
        [1001, 'https://img.example.com/p1.jpg', 1, 1],
        [1001, 'https://img.example.com/p1b.jpg', 2, 0],
        [1002, 'https://img.example.com/p2.jpg', 1, 1],
    ]);

    dbInsert('shop_hs_codes', ['id', 'code', 'description', 'parent_code', 'section'], [
        [1, '620442', "Women's dresses", '6204', 'Textiles'],
        [2, '640399', 'Footwear', '6403', 'Footwear'],
    ]);
    dbInsert('shop_product_hs_codes', ['product_id', 'hs_code_id', 'is_primary'], [
        [1001, 1, 1],
        [1002, 2, 1],
    ]);
    dbInsert('shop_tariff_rules', ['dest_country_id', 'hs_code_id', 'duty_rate', 'vat_rate', 'duty_free_threshold', 'vat_free_threshold'], [
        [1, 1, 12.000, 0.00, 800.00, 0.00],
        [3, 1, 12.000, 20.00, 135.00, 135.00],
        [4, 1, 12.000, 19.00, 150.00, 150.00],
    ]);
    dbInsert('shop_vat_settings', ['country_id', 'vat_rate', 'reduced_vat_rate', 'ioss_enabled', 'duty_free_threshold', 'vat_free_threshold'], [
        [1, 0.00, 0.00, 0, 800.00, 0.00],
        [2, 5.00, 0.00, 0, 150.00, 0.00],
        [3, 20.00, 5.00, 1, 135.00, 135.00],
        [4, 19.00, 7.00, 1, 150.00, 150.00],
        [5, 10.00, 8.00, 0, 0.00, 0.00],
    ]);

    dbInsert('shop_logistics_companies', ['id', 'name', 'code', 'tracking_url', 'estimated_days', 'status'], [
        [1, 'DHL Express', 'DHL', 'https://www.dhl.com/track?num=', '3-5', 1],
        [2, 'UPS', 'UPS', 'https://www.ups.com/track?num=', '5-8', 1],
    ]);
    dbInsert('shop_shipping_zones', ['id', 'name', 'countries', 'status'], [
        [1, '北美区', '["US","CA"]', 1],
        [2, '西欧区', '["GB","DE"]', 1],
        [3, '亚太区', '["JP"]', 1],
    ]);
    dbInsert('shop_shipping_zone_rates', ['zone_id', 'logistics_id', 'weight_from', 'weight_to', 'price', 'per_kg_price'], [
        [1, 1, 0.000, 1.000, 25.00, 8.00],
        [1, 2, 0.000, 1.000, 20.00, 6.00],
        [2, 1, 0.000, 1.000, 30.00, 10.00],
        [3, 1, 0.000, 1.000, 18.00, 6.00],
    ]);

    dbInsert('shop_payment_gateways', ['id', 'code', 'name', 'mode', 'status'], [
        [1, 'stripe', 'Stripe', 'sandbox', 1],
        [2, 'paypal', 'PayPal', 'sandbox', 1],
        [3, 'klarna', 'Klarna', 'sandbox', 1],
    ]);
    dbInsert('shop_payment_gateway_methods', ['gateway_id', 'method_code', 'countries', 'currencies', 'min_amount', 'max_amount', 'status'], [
        [1, 'card', '["US","CA","GB","DE","JP"]', '["USD","CAD","GBP","EUR","JPY"]', 1.00, 999999.00, 1],
        [2, 'paypal', '["US","CA","GB","DE","JP"]', '["USD","CAD","GBP","EUR","JPY"]', 1.00, 999999.00, 1],
        [3, 'klarna_paylater', '["DE","GB"]', '["EUR","GBP"]', 35.00, 5000.00, 1],
    ]);

    dbInsert('shop_banners', ['id', 'title', 'image', 'link_url', 'position', 'countries', 'sort', 'status', 'start_at', 'end_at'], [
        [1, 'Summer Sale', 'https://img.example.com/b1.jpg', '/products', 'home', null, 1, 1, date('Y-m-d H:i:s', strtotime('-1 hour')), date('Y-m-d H:i:s', strtotime('+30 days'))],
        [2, 'Category Banner', 'https://img.example.com/b2.jpg', '/categories', 'category', '["US"]', 1, 1, null, null],
        [3, 'Hidden Banner', 'https://img.example.com/b3.jpg', '', 'home', null, 1, 0, null, null],
    ]);
    dbInsert('shop_coupons', ['id', 'title', 'type', 'value', 'min_amount', 'total_qty', 'received_qty', 'per_user_limit', 'scope_type', 'scope_ids', 'countries', 'new_user_only', 'start_at', 'end_at', 'status'], [
        [1, '满100减20', 1, 20.00, 100.00, 100, 0, 1, 'all', null, null, 0, date('Y-m-d H:i:s', strtotime('-1 day')), date('Y-m-d H:i:s', strtotime('+30 days')), 1],
        [2, '已过期优惠券', 2, 10.00, 0.00, 100, 0, 1, 'all', null, null, 0, date('Y-m-d H:i:s', strtotime('-30 days')), date('Y-m-d H:i:s', strtotime('-1 day')), 1],
        [3, '新用户专享', 3, 5.00, 0.00, 100, 0, 1, 'all', null, null, 1, date('Y-m-d H:i:s', strtotime('-1 day')), date('Y-m-d H:i:s', strtotime('+30 days')), 1],
    ]);

    dbInsert('shop_cms_pages', ['id', 'slug', 'type', 'status', 'published_at'], [
        [1, 'about-us', 'page', 1, $now],
        [2, 'privacy-policy', 'page', 1, $now],
    ]);
    dbInsert('shop_cms_page_translations', ['page_id', 'locale', 'title', 'content'], [
        [1, 'en', 'About Us', '<p>GlobalShop is a cross-border e-commerce platform.</p>'],
        [1, 'zh_CN', '关于我们', '<p>GlobalShop 是跨境电商平台。</p>'],
        [2, 'en', 'Privacy Policy', '<p>We respect your privacy.</p>'],
    ]);
    dbInsert('shop_faq_translations', ['category', 'locale', 'question', 'answer', 'sort', 'status'], [
        ['shipping', 'en', 'How long does shipping take?', '7-15 business days.', 1, 1],
        ['shipping', 'ja', '配送にはどのくらいかかりますか？', '7〜15営業日です。', 1, 1],
        ['returns', 'en', 'How do I return an item?', 'Submit a return request in your order page.', 1, 1],
        ['payment', 'en', 'Which payment methods are accepted?', 'Stripe, PayPal and Klarna.', 1, 1],
    ]);

    dbInsert('shop_size_charts', ['id', 'category_id', 'type', 'name'], [
        [1, 1, 'clothing', 'Dress Size Chart'],
    ]);
    dbInsert('shop_size_chart_values', ['chart_id', 'region', 'size_label', 'measurement_cm'], [
        [1, 'US', 'S', 86.00],
        [1, 'US', 'M', 91.00],
        [1, 'EU', '36', 86.00],
        [1, 'EU', '38', 91.00],
        [1, 'JP', '9', 91.00],
    ]);

    dbInsert('shop_flash_sales', ['id', 'title', 'start_at', 'end_at', 'status'], [
        [1, '限时秒杀', date('Y-m-d H:i:s', strtotime('-1 hour')), date('Y-m-d H:i:s', strtotime('+24 hours')), 1],
        [2, '已结束秒杀', date('Y-m-d H:i:s', strtotime('-48 hours')), date('Y-m-d H:i:s', strtotime('-24 hours')), 1],
    ]);
    dbInsert('shop_flash_sale_skus', ['flash_sale_id', 'sku_id', 'price', 'stock', 'limit_per_user'], [
        [1, 2001, 19.99, 10, 1],
    ]);
    dbInsert('shop_group_buys', ['id', 'title', 'sku_id', 'group_price', 'required_count', 'expire_hours', 'start_at', 'end_at', 'status'], [
        [1, '2人成团享低价', 2001, 24.99, 2, 24, date('Y-m-d H:i:s', strtotime('-1 hour')), date('Y-m-d H:i:s', strtotime('+24 hours')), 1],
    ]);

    dbInsert('shop_membership_levels', ['id', 'name', 'level', 'min_score', 'icon', 'discount_rate', 'free_shipping', 'status'], [
        [1, 'Bronze', 1, 0, '', 0.00, 0, 1],
        [2, 'Silver', 2, 1000, '', 3.00, 0, 1],
        [3, 'Gold', 3, 5000, '', 5.00, 1, 1],
    ]);
    dbInsert('shop_membership_benefits', ['level_id', 'benefit_type', 'benefit_value'], [
        [2, 'discount', '3%'],
        [3, 'free_shipping', '1'],
        [3, 'priority', '1'],
    ]);

    dbInsert('shop_settings', ['key', 'value', 'group', 'remark'], [
        ['site_name', 'GlobalShop', 'general', '站点名称'],
        ['support_email', 'support@example.com', 'general', '客服邮箱'],
        ['default_currency', 'USD', 'general', '默认币种'],
        ['order_auto_cancel_minutes', '1440', 'shop', '订单自动取消分钟'],
        ['risk_threshold', '80', 'risk', '风控阈值'],
    ]);

    dbInsert('shop_gift_cards', ['id', 'code', 'denomination', 'balance', 'currency_code', 'receiver_email', 'message', 'status', 'expire_at'], [
        [1, 'GIFT-TEST-001', 50.00, 50.00, 'USD', '', 'Test gift card', 1, null],
        [2, 'GIFT-TEST-002', 50.00, 0.00, 'USD', '', 'Used card', 2, null],
        [3, 'GIFT-TEST-003', 50.00, 50.00, 'USD', '', 'Expired card', 1, '2020-01-01'],
    ]);

    dbInsert('shop_warehouses', ['id', 'name', 'country_id', 'address', 'contact', 'is_default', 'is_return_warehouse', 'is_overseas', 'status'], [
        [1, '深圳总仓', 1, 'Shenzhen, CN', 'Ops', 1, 1, 0, 1],
        [2, '德国海外仓', 4, 'Frankfurt, DE', 'DE Ops', 0, 1, 1, 1],
        [3, '日本海外仓', 5, 'Tokyo, JP', 'JP Ops', 0, 0, 1, 1],
    ]);

    dbInsert('shop_notifications', ['id', 'user_id', 'title', 'content', 'type', 'target_type', 'target_id'], [
        [1, 0, '欢迎来到 GlobalShop', '感谢注册，祝您购物愉快！', 'system', '', 0],
        [2, 0, '限时优惠', '全场满100减20', 'promotion', 'page', 1],
    ]);

    dbInsert('shop_compliance_categories', ['id', 'code', 'name', 'description'], [
        [1, 'CE', 'CE标志', '欧盟强制性安全认证'],
        [2, 'FDA', 'FDA认证', '美国食品药品监管'],
        [3, 'RoHS', 'RoHS', '有害物质限制'],
    ]);
    dbInsert('shop_country_compliance_rules', ['country_id', 'compliance_category_id', 'rule', 'restriction_reason'], [
        [1, 2, 'allowed', ''],
        [4, 1, 'allowed', ''],
        [5, 3, 'allowed', ''],
    ]);
    dbInsert('shop_product_compliance', ['product_id', 'compliance_category_id', 'cert_no', 'expire_at'], [
        [1001, 1, 'CE2024001', '2028-01-01'],
    ]);

    dbInsert('shop_users', ['id', 'nickname', 'email', 'password', 'status', 'invite_code'], [
        [1, 'Reviewer', 'reviewer@test.local', 'x', 1, 'REV00001'],
    ]);
    dbInsert('shop_product_reviews', ['id', 'user_id', 'product_id', 'order_id', 'sku_id', 'rating', 'content', 'images', 'status'], [
        [1, 1, 1001, 0, 2001, 5, 'Great dress, fast shipping!', '[]', 1],
        [2, 1, 1001, 0, 2002, 4, 'Nice but runs small.', '[]', 1],
    ]);

    dbInsert('shop_point_rules', ['id', 'code', 'name', 'points', 'limit_type', 'limit_value', 'status'], [
        [1, 'register', '注册送积分', 100, 'unlimited', 0, 1],
        [2, 'order', '下单送积分', 10, 'unlimited', 0, 1],
        [3, 'review', '评价送积分', 50, 'unlimited', 0, 1],
    ]);

    echo "种子数据写入完成\n";
}
