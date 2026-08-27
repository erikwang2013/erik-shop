# क्रॉस-बॉर्डर ई-कॉमर्स प्लेटफ़ॉर्म — डिज़ाइन दस्तावेज़

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. डेटाबेस डिज़ाइन

### 1.1 नामकरण मानक

- तालिका उपसर्ग: `erik_`
- प्राथमिक कुंजी: `id BIGINT UNSIGNED NOT NULL` (snowflake द्वारा जनित, गैर-ऑटोइंक्रीमेंट)
- टाइमस्टैम्प: `created_at`, `updated_at`, `deleted_at` (सॉफ्ट डिलीट)
- इंजन: InnoDB, वर्णसेट: utf8mb4_unicode_ci

### 1.2 मॉड्यूल विभाजन (110 तालिकाएँ)

| मॉड्यूल | तालिका संख्या | मुख्य तालिकाएँ |
|------|------|--------|
| उपयोगकर्ता और खाते | 7 | users, user_addresses, user_social_accounts, user_kyc, user_wishlists, membership_levels, membership_benefits |
| उत्पाद और श्रेणियाँ | 19 | products, product_translations, product_skus, product_sku_prices, product_images, product_attrs, product_reviews, product_compliance, product_hs_codes, compliance_categories |
| लेनदेन | 8 | orders, order_items, order_logs, payments, refunds, return_orders, return_labels, order_documents |
| भुगतान और धनराशि | 5 | payment_gateways, payment_gateway_methods, platform_settlements, supplier_settlements, currency_exchange_gains_losses |
| लॉजिस्टिक्स | 9 | logistics_companies, shipping_zones, shipping_zone_rates, warehouses, shipments, shipping_insurances, inventory_logs, inventory_transfers |
| सीमा शुल्क और कर | 5 | hs_codes, tariff_rules, vat_settings, country_compliance_rules |
| मार्केटिंग | 8 | coupons, user_coupons, flash_sales, flash_sale_skus, group_buys, affiliate_links, affiliate_commissions, affiliate_payouts |
| आपूर्ति श्रृंखला | 5 | suppliers, purchase_orders, purchase_order_items, quality_inspections, quality_inspection_items |
| जोखिम प्रबंधन और अनुपालन | 5 | risk_rules, risk_logs, privacy_requests, cookie_consents, privacy_policy_versions |
| बहु-प्लेटफ़ॉर्म | 9 | shops, platform_accounts, platform_listings, platform_orders, platform_order_items, merchants, merchant_products, merchant_settlements |
| सामग्री और अनुभव | 14 | cms_pages, cms_page_translations, size_charts, size_chart_values, product_feeds, notifications, email_templates, price_alerts, search_logs, operation_logs, settings |
| सदस्यता और B2B | 9 | subscriptions, subscription_orders, point_rules, point_logs, gift_cards, b2b_prices, b2b_verifications, b2b_quotes |
| ग्राहक सेवा | 5 | chat_sessions, chat_messages, knowledge_base, knowledge_base_translations, faq_translations |
| AB परीक्षण | 3 | ab_tests, ab_test_variants, ab_test_results |
| API प्रशासन | 2 | api_rate_limits, api_docs |
| मूल डेटा | 3 | countries, currencies, exchange_rates |

### 1.3 प्लेटफ़ॉर्म ट्रैकिंग फ़ील्ड

| तालिका | फ़ील्ड | विवरण |
|----|------|------|
| orders | platform VARCHAR(16) | ऑर्डर प्लेटफ़ॉर्म |
| payments | platform VARCHAR(16) | भुगतान प्लेटफ़ॉर्म |
| operation_logs | platform VARCHAR(16) | संचालन प्लेटफ़ॉर्म |
| users | last_login_platform VARCHAR(16) | अंतिम लॉगिन प्लेटफ़ॉर्म |
| search_logs | platform VARCHAR(16) | खोज प्लेटफ़ॉर्म |
| chat_messages | platform VARCHAR(16) | संदेश स्रोत |

---

## 2. API डिज़ाइन

API संस्करण नियंत्रण, मिडलवेयर पाइपलाइन, एंडपॉइंट आँकड़े और समान प्रतिक्रिया मानक — विवरण के लिए देखें [API इंटरफ़ेस दस्तावेज़](api.md)।

---

## 3. सुरक्षा डिज़ाइन

### 3.1 SecurityMiddleware — security-php के 31 डिटेक्टरों का आवरण

| # | प्रकार | त्रुटि कोड | Service | Admin |
|---|------|-----------|---------|-------|
| 1 | XSS | 40001 | ✅ | ✅ |
| 2 | SQL इंजेक्शन | 40002 | ✅ | ✅ |
| 3 | CRLF | 40003 | ✅ | ✅ |
| 4 | पाथ ट्रैवर्सल | 40004 | ✅ | ✅ |
| 5 | Body बहुत बड़ा | 40005 | ✅ | ✅ |
| 6 | Content-Type | 40006 | ✅ | ✅ |
| 7 | फ़ाइल अपलोड | 40009 | ✅ | ✅ |
| 8 | सुरक्षित प्रतिक्रिया हेडर | — | ✅ | ✅ |
| 9 | ब्रूट फ़ोर्स | 40008 | ✅ | ✅ |
| 10 | XXE | 40010 | ✅ | ✅ |
| 11 | SSRF | 40011 | ✅ | ✅ |
| 12 | HTTP विधि | 40012 | ✅ | ✅ |
| 13 | Host हेडर | 40013 | ✅ | — |
| 14 | संवेदनशील डेटा मास्किंग | — | ✅ | ✅ |
| 15 | CORS श्वेतसूची | — | ⚠️ | ⚠️ |

### 3.2 तीन-परत एन्क्रिप्शन

| परत | तकनीक | पैकेज |
|------|------|-----|
| ट्रांसमिशन परत | AES-256-CBC | erikwang2013/encryption |
| डेटाबेस परत | Encryptable trait | erikwang2013/encryptable (Maize) |
| ID अस्पष्टीकरण | Hashids | erikwang2013/hashids |

---

## 4. उच्च समवर्ती डिज़ाइन

### 4.1 रेट लिमिट

टोकन बकेट स्लाइडिंग विंडो (Redis ZSET, support\Redis फेसडे के माध्यम से): डिफ़ॉल्ट 60s/100 बार, लॉगिन 10 बार/60s, रजिस्ट्रेशन 5 बार/300s, सोशल लॉगिन 5 बार/300s, भुगतान 5 बार/60s, ऑर्डर 3 बार/10s, खोज 10 बार/1s

### 4.2 सर्किट ब्रेकर और डिग्रेडेशन

Redis सर्किट ब्रेकर (`app\common\CircuitBreaker`): पेमेंट गेटवे/सोशल लॉगिन जैसे बाहरी API कॉल यूनिफाइड रूप से `CircuitBreaker::call()` से होकर गुजरते हैं — 5 लगातार विफलताएँ 30s के लिए सर्किट खोलती हैं, TTL समाप्त होने पर अगला रिक्वेस्ट स्वतः हाफ-ओपन प्रोब भेजता है, सफलता पर रीसेट। बिज़नेस अपवाद व्हाइटलिस्ट (अमान्य कार्ड/अमान्य token) फेल्योर में नहीं गिने जाते, ताकि हमलावर अमान्य रिक्वेस्ट से डिपेंडेंसी सेवा को बंद न कर सकें; Redis अनुपलब्ध होने पर स्वतः डिग्रेड होकर अनुमति दी जाती है। ब्रेक के दौरान इंटरफ़ेस 503「सेवा अस्थायी रूप से अनुपलब्ध」लौटाता है।

### 4.3 Redis उपयोग

Redis का उपयोग रेट लिमिट टोकन बकेट (`support\Redis` फेसडे), मानव-सत्यापन कोड और Session स्टोरेज के लिए होता है; व्यावसायिक डेटा पर एप्लिकेशन-लेयर कैश नहीं किया जाता, सीधे MySQL से पढ़ा जाता है (रीड/राइट स्प्लिटिंग + कनेक्शन पूल)।

### 4.4 कनेक्शन पूल

MySQL: 50max/10min/2s टाइमआउट | रीड/राइट स्प्लिटिंग: 30max/5min (2 रीड रेप्लिका, sticky=true) | Redis: 30max/5min

---

## 5. अंतर्राष्ट्रीयकरण (i18n)

- इंटरफ़ेस: zh_CN, zh_HK, en, ja, ko
- सामग्री: erik_product_translations — locale के अनुसार स्वतंत्र पंक्तियाँ
- मूल्य: erik_product_sku_prices — मुद्रा के अनुसार स्वतंत्र मूल्य निर्धारण
- हेडर: Accept-Language + API-Version

## 6. API दस्तावेज़

कंट्रोलर एनोटेशन के आधार पर hg/apidoc द्वारा स्वचालित रूप से जनित, विवरण के लिए देखें [API इंटरफ़ेस दस्तावेज़](api.md)। प्रारंभ के बाद `/apidoc/` पर जाएँ।

## 7. परीक्षण

22 tests / 45 assertions — ALL PASS

```bash
cd service && php vendor/bin/phpunit tests/
# SecurityTest (12) + JwtTest (4) + ApiResponseTest (3) + RedisFacadeTest (3)
```

विवरण के लिए देखें: [कार्यक्षमता डिज़ाइन दस्तावेज़](features.md) | [पूर्ण आर्किटेक्चर दस्तावेज़](architecture-full.md) | [तैनाती दस्तावेज़](deployment.md)
