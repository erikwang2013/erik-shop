# ক্রস-বর্ডার ই-কমার্স প্ল্যাটফর্ম — ডিজাইন ডকুমেন্ট

> এই নথিটি মূল চীনা ডকুমেন্টেশনের মেশিন অনুবাদ। মূল: [চীনা মূল](../../design.md).

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. ডেটাবেস ডিজাইন

### 1.1 নামকরণ নিয়ম

- টেবিল প্রিফিক্স: `erik_`
- প্রাইমারি কী: `id BIGINT UNSIGNED NOT NULL` (snowflake দিয়ে তৈরি, অটো-ইনক্রিমেন্ট নয়)
- টাইমস্ট্যাম্প: `created_at`, `updated_at`, `deleted_at` (সফট ডিলিট)
- ইঞ্জিন: InnoDB, ক্যারেক্টার সেট: utf8mb4_unicode_ci

### 1.2 মডিউল বিভাজন (110 টেবিল)

| মডিউল | টেবিল সংখ্যা | মূল টেবিল |
|------|------|--------|
| ইউজার ও অ্যাকাউন্ট | 7 | users, user_addresses, user_social_accounts, user_kyc, user_wishlists, membership_levels, membership_benefits |
| প্রোডাক্ট ও ক্যাটাগরি | 19 | products, product_translations, product_skus, product_sku_prices, product_images, product_attrs, product_reviews, product_compliance, product_hs_codes, compliance_categories |
| ট্রানজেকশন | 8 | orders, order_items, order_logs, payments, refunds, return_orders, return_labels, order_documents |
| পেমেন্ট ও ফান্ড | 5 | payment_gateways, payment_gateway_methods, platform_settlements, supplier_settlements, currency_exchange_gains_losses |
| লজিস্টিক | 9 | logistics_companies, shipping_zones, shipping_zone_rates, warehouses, shipments, shipping_insurances, inventory_logs, inventory_transfers |
| কাস্টমস ও ট্যাক্স | 5 | hs_codes, tariff_rules, vat_settings, country_compliance_rules |
| মার্কেটিং | 8 | coupons, user_coupons, flash_sales, flash_sale_skus, group_buys, affiliate_links, affiliate_commissions, affiliate_payouts |
| সাপ্লাই চেইন | 5 | suppliers, purchase_orders, purchase_order_items, quality_inspections, quality_inspection_items |
| রিস্ক ও কমপ্লায়েন্স | 5 | risk_rules, risk_logs, privacy_requests, cookie_consents, privacy_policy_versions |
| মাল্টি-প্ল্যাটফর্ম | 9 | shops, platform_accounts, platform_listings, platform_orders, platform_order_items, merchants, merchant_products, merchant_settlements |
| কনটেন্ট ও এক্সপেরিয়েন্স | 14 | cms_pages, cms_page_translations, size_charts, size_chart_values, product_feeds, notifications, email_templates, price_alerts, search_logs, operation_logs, settings |
| সাবস্ক্রিপশন ও B2B | 9 | subscriptions, subscription_orders, point_rules, point_logs, gift_cards, b2b_prices, b2b_verifications, b2b_quotes |
| কাস্টমার সার্ভিস | 5 | chat_sessions, chat_messages, knowledge_base, knowledge_base_translations, faq_translations |
| AB টেস্ট | 3 | ab_tests, ab_test_variants, ab_test_results |
| API গভর্ন্যান্স | 2 | api_rate_limits, api_docs |
| বেস ডেটা | 3 | countries, currencies, exchange_rates |

### 1.3 প্ল্যাটফর্ম ট্র্যাকিং ফিল্ড

| টেবিল | ফিল্ড | বিবরণ |
|----|------|------|
| orders | platform VARCHAR(16) | অর্ডার দেওয়ার প্ল্যাটফর্ম |
| payments | platform VARCHAR(16) | পেমেন্ট প্ল্যাটফর্ম |
| operation_logs | platform VARCHAR(16) | অপারেশন প্ল্যাটফর্ম |
| users | last_login_platform VARCHAR(16) | শেষ লগইনের প্ল্যাটফর্ম |
| search_logs | platform VARCHAR(16) | সার্চ প্ল্যাটফর্ম |
| chat_messages | platform VARCHAR(16) | মেসেজ উৎস |

---

## 2. API ডিজাইন

API ভার্সন কন্ট্রোল, মিডলওয়্যার পাইপলাইন, এন্ডপয়েন্ট পরিসংখ্যান ও ইউনিফাইড রেসপন্স স্ট্যান্ডার্ডের জন্য [API ইন্টারফেস ডকুমেন্ট](api.md) দেখুন।

---

## 3. নিরাপত্তা ডিজাইন

### 3.1 SecurityMiddleware-এ security-php 31 ডিটেক্টর এনক্যাপসুলেশন

| # | টাইপ | এরর কোড | Service | Admin |
|---|------|--------|---------|-------|
| 1 | XSS | 40001 | ✅ | ✅ |
| 2 | SQL ইনজেকশন | 40002 | ✅ | ✅ |
| 3 | CRLF | 40003 | ✅ | ✅ |
| 4 | পাথ ট্রাভার্সাল | 40004 | ✅ | ✅ |
| 5 | Body অতিরিক্ত বড় | 40005 | ✅ | ✅ |
| 6 | Content-Type | 40006 | ✅ | ✅ |
| 7 | ফাইল আপলোড | 40009 | ✅ | ✅ |
| 8 | সিকিউরিটি রেসপন্স হেডার | — | ✅ | ✅ |
| 9 | ব্রুট ফোর্স | 40008 | ✅ | ✅ |
| 10 | XXE | 40010 | ✅ | ✅ |
| 11 | SSRF | 40011 | ✅ | ✅ |
| 12 | HTTP মেথড | 40012 | ✅ | ✅ |
| 13 | Host হেডার | 40013 | ✅ | — |
| 14 | সংবেদনশীল ডেটা ডেসেনসিটাইজেশন | — | ✅ | ✅ |
| 15 | CORS হোয়াইটলিস্ট | — | ⚠️ | ⚠️ |

### 3.2 তিন স্তরের এনক্রিপশন

| স্তর | প্রযুক্তি | প্যাকেজ |
|------|------|-----|
| ট্রান্সপোর্ট স্তর | AES-256-CBC | erikwang2013/encryption |
| ডেটাবেস স্তর | Encryptable trait | erikwang2013/encryptable (Maize) |
| ID অবফাসকেশন | Hashids | erikwang2013/hashids |

---

## 4. হাই কনকারেন্সি ডিজাইন

### 4.1 রেট লিমিট

টোকেন বাকেট স্লাইডিং উইন্ডো (Redis ZSET, support\Redis ফ্যাসাডের মাধ্যমে): ডিফল্ট 60s/100 বার, লগইন 10 বার/60s, রেজিস্ট্রেশন 5 বার/300s, সোশ্যাল লগইন 5 বার/300s, পেমেন্ট 5 বার/60s, অর্ডার 3 বার/10s, সার্চ 10 বার/1s

### 4.2 সার্কিট ব্রেকার ও ডিগ্রেডেশন

Redis সার্কিট ব্রেকার (`app\common\CircuitBreaker`): পেমেন্ট গেটওয়ে/সোশ্যাল লগইনসহ সব বাহ্যিক API কল একীভূতভাবে `CircuitBreaker::call()` দিয়ে যায় — ধারাবাহিক 5টি ব্যর্থতায় সার্কিট 30s খোলে, TTL শেষ হলে পরবর্তী রিকোয়েস্ট স্বয়ংক্রিয়ভাবে হাফ-ওপেন প্রোব চালায়, সফল হলে রিসেট হয়। বিজনেস এরর হোয়াইটলিস্ট (অবৈধ কার্ড/অবৈধ টোকেন) ব্যর্থতা হিসেবে গণনা করা হয় না, যাতে আক্রমণকারীরা অবৈধ রিকোয়েস্ট দিয়ে নির্ভরশীল সার্ভিস ডাউন করতে না পারে; Redis ডাউন থাকলে স্বয়ংক্রিয় fail-open। সার্কিট খোলা থাকাকালীন ইন্টারফেস 503 «সার্ভিস সাময়িকভাবে অনুপলব্ধ» রিটার্ন করে।

### 4.3 Redis-এর ব্যবহার

Redis রেট লিমিট টোকেন বাকেট (`support\Redis` ফ্যাসাড), হিউম্যান ভেরিফিকেশন কোড ও Session স্টোরেজে ব্যবহৃত হয়; বিজনেস ডেটা অ্যাপ্লিকেশন-লেভেল ক্যাশ করা হয় না, সরাসরি MySQL থেকে পড়া হয় (রিড-রাইট সেপারেশন + কানেকশন পুল)। এছাড়া Redis দিয়ে অ্যাডমিন প্যানেল থেকে service-এ CDN গ্লোবাল অন/অফ স্টেট প্রচার করা হয় (শেয়ার্ড কী, prefix `shop:`, TTL 60s)।

### 4.4 কানেকশন পুল

MySQL: 50max/10min/2s টাইমআউট | রিড-রাইট সেপারেশন: 30max/5min (2 রিড রেপ্লিকা, sticky=true) | Redis: 30max/5min

---

## 5. ইন্টারন্যাশনালাইজেশন

- ইন্টারফেস: zh_CN, zh_HK, en, ja, ko
- কনটেন্ট: erik_product_translations-এ locale অনুযায়ী আলাদা রো
- প্রাইস: erik_product_sku_prices-এ কারেন্সি অনুযায়ী আলাদা প্রাইসিং
- হেডার: Accept-Language + API-Version

## 6. API ডকুমেন্টেশন

hg/apidoc দিয়ে কন্ট্রোলার অ্যানোটেশন অনুযায়ী স্বয়ংক্রিয়ভাবে তৈরি হয়, [API ইন্টারফেস ডকুমেন্ট](api.md) দেখুন। চালু হলে `/apidoc/` অ্যাক্সেস করুন।

## 7. টেস্ট

22 tests / 45 assertions — ALL PASS

```bash
cd service && php vendor/bin/phpunit tests/
# SecurityTest (12) + JwtTest (4) + ApiResponseTest (3) + RedisFacadeTest (3)
```

বিস্তারিত: [ফিচার ডিজাইন ডকুমেন্ট](features.md) | [সম্পূর্ণ আর্কিটেকচার ডকুমেন্ট](architecture-full.md) | [ডিপ্লয়মেন্ট ডকুমেন্ট](deployment.md)
