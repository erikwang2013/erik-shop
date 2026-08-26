# Erik Shop परियोजना योजना (टीम उत्पादन)

> **निर्माण समय**: 2026-08
> **निर्माण विधि**: बहु-एजेंट टीम सहयोग (7 क्षेत्र समानांतर अनुसंधान → सिस्टम आर्किटेक्ट एकीकरण → समीक्षा इंजीनियर पुनः सत्यापन)
> **आधार**: `PLAN-RESEARCH.md` (7 क्षेत्रीय अनुसंधान विवरण), `../../README.md`, प्रत्येक उप-प्रोजेक्ट का `CLAUDE.md`
> **लागू अवधि**: 3-6 महीने (4 चरण)
> **समीक्षा रिकॉर्ड**: 2026-08 समीक्षा इंजीनियर ने कोड के विरुद्ध 18 कथनों की पुनः जाँच की (16 सही, 2 आंशिक रूप से सही क्योंकि वर्कस्पेस में पहले ही ठीक हो चुके थे); इस संस्करण में समीक्षा समायोजन शामिल हैं (PosterVerify जारी करने वाला इंटरफ़ेस, जोखिम समीक्षा निकास, Flutter पथ, कार्यान्वयन स्थिति चिह्न आदि)।

## ०, वर्तमान कार्यान्वयन स्थिति (समीक्षा के समय जाँची गई)

> `git status`/`git diff` के आधार पर वास्तविक सत्यापन; ✅=पूर्ण (वर्कस्पेस में कमिट नहीं), 🔄=प्रगति पर, ⬜=प्रारंभ नहीं।

| मद | स्थिति | विवरण |
|---|---|---|
| admin के दो घातक कंट्रोलर सिग्नेचर सुधार (ShopOrder/ShopPayment में `: array`/`: Response` जोड़े गए) | ✅ | सुधार के बाद 82/82 कंट्रोलर रिफ्लेक्शन लोड सफल (सुधार से पहले 2 Fatal) |
| PHPStan गेट | ✅ | `make check` वास्तविक परीक्षण `[OK] No errors`; PHPStan 2.2.8 ने neon `memoryLimit` पैरामीटर हटा दिया है, अब Makefile/CI से `--memory-limit=1G` पास किया जाता है |
| ShopDashboardController json सिग्नेचर + दृश्य fetch URL | ✅ | `$this->json(0,'ok',$data)` + `/ShopDashboard/kpi` क्लास-नाम रूटिंग |
| CI में composer audit + phpstan जोड़े गए | ✅ | `.github/workflows/ci.yml` में दो नए चरण (YAML सत्यापित) |
| `scripts/smoke_controllers.php` पुनरावृत्ति-रोधी स्मोक टेस्ट | 🔄 | चरण एक डिलीवरेबल देखें |
| PosterVerify जारी करने वाला इंटरफ़ेस (`POST /api/poster/verify`) | ✅ | समीक्षा में नई खोज + कार्यान्वित: `PosterController` (math अंकगणितीय प्रश्न) + रूट; 8789 पोर्ट पर पूर्ण श्रृंखला वास्तविक परीक्षण पास (challenge→verify→मिडलवेयर पास→एक-बार उपभोग) |
| 🔄 नई खोज P0: Encryptable खाली IV रजिस्ट्रेशन रोकता है | ✅ | सुधारित: `app/common/SecureEncrypter.php` (स्पष्ट 16-बाइट शून्य IV, पुराने डेटा के साथ बाइट-स्तर संगत) + `support/bootstrap.php` रिज़ॉल्वर रजिस्ट्रेशन; वास्तविक परीक्षण: रजिस्ट्रेशन सफल, लॉगिन डिक्रिप्शन सामान्य |
| 🔄 नई खोज P0: एन्क्रिप्टेड फ़ील्ड क्वेरी करने योग्य नहीं (email) | ✅ | सुधारित: `erik_users.email_hash` (HMAC-SHA256 इंडेक्स कॉलम, install.sql + ALTER + बैकफ़िल); AuthController register/login और SocialAuthController अब email_hash से क्वेरी करते हैं; वास्तविक परीक्षण: रजिस्ट्रेशन सफल/डुप्लिकेट रजिस्ट्रेशन 422/लॉगिन सफल/गलत पासवर्ड 401 |
| 🔄 नई खोज P0: HASHIDS_SALT प्लेसहोल्डर/पढ़ा नहीं जा रहा | ✅ | सुधारित: `config/hashids.php` main.salt `getenv('HASHIDS_SALT')` पढ़ता है; इस वातावरण के `.env` में यादृच्छिक salt जनित (मूल रूप से change_me प्लेसहोल्डर था, और कॉन्फ़िगरेशन में खाली salt लिखा था जिससे fail-closed अपवाद होता था) |
| Quick Win #3: व्यावसायिक सीड डेटा स्वचालित आयात | ✅ | नया `service/database/seeders/run.php` (इडेमपोटेंट: countries 23 + logistics 3 + shipping zones 3 + rates 3 + gateways 2 + methods 2 + hs_codes 8 + tariff_rules 7); वास्तविक परीक्षण: पुनः चलाने पर 0 नया 51 स्किप; /api/countries, /api/payment/methods, /api/shipping/calculate (उत्तर अमेरिका क्षेत्र DHL 12.24), /api/tariff/estimate सभी उपलब्ध |
| 🔄 नई खोज: मॉडल गलत encryptable (name जैसे गैर-संवेदनशील फ़ील्ड) | ✅ | 30+ मॉडल name जैसे सार्वजनिक फ़ील्ड एन्क्रिप्ट कर रहे थे: नाम से क्वेरी/सॉर्ट टूट रहा था और छोटे फ़ील्ड में साइफरटेक्स्ट नहीं समाता था। सभी साफ़ किए गए: सीड में 4 मॉडल (पिछले राउंड) + बैच में 17 मॉडल (Categories/Currencies/Shops/Suppliers.name/Merchants.store_name आदि), email/mobile/real_name/api_key/access_token जैसे वास्तविक संवेदनशील फ़ील्ड बनाए रखे |
| 🔄 नई खोज: मॉडल में Eloquent संबंधों की कमी | ✅ | PaymentGatewayMethods.gateway, ShippingZoneRates.logistics/zone गायब होने से /api/payment/methods, /api/shipping/calculate 500 दे रहे थे, जोड़े गए |
| चरण एक: OrderController वास्तविक बिलिंग | ✅ | store() में कूपन (न्यूनतम-राशि छूट/डिस्काउंट/फिक्स्ड, user_coupons + used_qty वेरिफिकेशन), शिपिंग शुल्क (ज़ोन + दर सीढ़ी न्यूनतम मूल्य), सीमा शुल्क/VAT (HS Code → गंतव्य देश दर) जोड़े गए; वास्तविक परीक्षण: 3×49.99=149.97 में 100 से अधिक पर 20 छूट → discount 20 + shipping 12.24 + tax 0 = pay 142.21, स्टॉक/वेरिफिकेशन/विवरण/लॉग पूर्ण श्रृंखला सत्यापन |
| 🔄 नई खोज P0: HashidsDecode पैरामीटर खो जाता है | ✅ | मिडलवेयर setPost($updates) पूर्ण प्रतिस्थापन था, किसी भी _id फ़ील्ड को डिकोड करने पर उसी अनुरोध के अन्य पैरामीटर (coupon_id/weight_grams आदि) खो जाते थे; array_merge में बदला, वास्तविक परीक्षण: बहु-पैरामीटर ऑर्डर सामान्य |
| 🔄 नई खोज: ऑर्डर श्रृंखला संबद्ध बग | ✅ | CouponController::claim का where कॉलम नाम गलत मान के रूप में लिखा था (whereColumn में बदला); Orders.address_snapshot JSON कॉलम में cast नहीं था (array cast जोड़ा); OrderLogs तालिका में updated_at नहीं था (मॉडल $timestamps=false) |
| चरण एक: InstallController में seeder एकीकरण | ✅ | इंस्टॉलेशन विज़ार्ड install.sql आयात के बाद स्वचालित रूप से service/database/seeders/run.php चलाता है (स्वतंत्र उप-प्रक्रिया autoload आइसोलेशन, विफलता पर केवल चेतावनी); साथ ही install.sql पाथ बग सुधारा (मूल base_path(false) admin/ से ऊपर रूट का install.sql नहीं ढूँढ पा रहा था, dirname में बदला) |
| चरण एक: हार्मनी ऑर्डर-भुगतान एकीकरण | ✅ | Checkout.ets में पता चयन, PosterVerify (challenge→verify), पूर्ण ऑर्डर पैरामीटर + X-Poster-Token, भुगतान आरंभ (payment/create); ApiClient headers/पैरामीटर विस्तार; **hvigor assembleHap संकलन पास** |
| चरण एक: Flutter ऑर्डर-भुगतान एकीकरण | ⚠️ कोडित, संकलन सत्यापन लंबित | checkout_screen में पता/मानव-सत्यापन/पूर्ण ऑर्डर/भुगतान आरंभ; register_screen में PosterVerify; api_client post headers समर्थन। **इस वातावरण का flutter SDK कैश रीड-ओनली है, संकलन असंभव**, स्थानीय `flutter analyze`/`flutter test` से सत्यापन आवश्यक (ब्रैकेट/संरचना स्थैतिक जाँच पारित) |
| चरण दो P1: जोखिम इंजन RiskEngine | ✅ | नया `app/common/RiskEngine.php` (email_domain अस्थायी ईमेल/velocity आवृत्ति/amount बड़ी राशि/address_mismatch/ip_reputation, Redis काउंटिंग); ऑर्डर/रजिस्ट्रेशन/भुगतान में साइड-बाय स्कोरिंग + RiskLogs; **वास्तविक परीक्षण**: अस्थायी ईमेल + बड़ी राशि का ऑर्डर → 80 अंक review → ऑर्डर status=8 समीक्षा लंबित, risk_score/risk_result/OrderLog जोखिम चिह्न पूर्ण |
| चरण दो P1: जोखिम समीक्षा निकास | ✅ | नया `POST /api/admin/orders/{id}/review` (AdminKeyMiddleware; approve→0 जारी/reject→5 अस्वीकृत, status=8 परमाणु संक्रमण + OrderLogs); **वास्तविक परीक्षण**: approve/reject/गलत key 403/डुप्लिकेट समीक्षा 422 सभी सही |
| चरण दो P1: KYC उपयोगकर्ता-पक्ष क्लोज्ड लूप | ✅ | नया KycController (POST /api/kyc सबमिट + GET /api/kyc/status क्वेरी, real_name/id_number Encryptable एन्क्रिप्टेड, status 0 लंबित/1 पास/2 अस्वीकृत); **वास्तविक परीक्षण**: सबमिट/क्वेरी सामान्य |
| चरण दो P1: भुगतान सख्तीकरण | ✅ | StripeGateway में स्पष्ट `request_three_d_secure=automatic` (3DS); Klarna/Adyen `PaymentGateway::make` throw प्लेसहोल्डर बनाए रखे (दस्तावेज़ सुधार लंबित) |
| 🔴 **नई खोज वैश्विक बग: HashidsDecode रूट पैरामीटर डिकोड अप्रभावी** | ✅ | webman कंट्रोलर विधि पैरामीटर findRoute द्वारा पकड़े गए मूल hashid से आते हैं (मिडलवेयर setParams प्रभावी नहीं)। समान रूप से सुधारित: `BaseApiController::decodedId()` हेल्पर + 17 स्थानों पर {id} रूट विधि प्रविष्टियाँ (ऑर्डर/उत्पाद/कार्ट/पता/पसंदीदा/समीक्षा/भुगतान स्थिति/रिटर्न/कूपन/सूचना/तुलना/रिफंड निष्पादन/समीक्षा); **वास्तविक परीक्षण**: ऑर्डर विवरण, उत्पाद विवरण, ऑर्डर रद्द, कार्ट update/destroy, कूपन लेना (hashid पथ) सभी पास; साथ ही Orders में items/logs/documents संबंध और Carts में sku संबंध जोड़े |
| चरण दो P1: समान विभाजन दर + विक्रेता विभाजन | ✅ | SettlementCron दर स्रोत `payment.gateway_fee.{gateway}` + `payment.platform_rate` पर समेकित (webhook के समान स्रोत, cron.* केवल संगतता फ़ॉलबैक); नया MerchantSettlements लेखन (order_items→MerchantProducts approved→merchant.commission_rate); **वास्तविक परीक्षण**: 162.21 ऑर्डर → प्लेटफ़ॉर्म 5% कमीशन 8.11 + stripe गेटवे शुल्क 5.00, विक्रेता 149.97@8% → कमीशन 12.00 सेटलमेंट 137.97 |
| चरण दो P1: चार-रेखा विभाजन पूर्ति (Supplier/Affiliate) | ✅ | schema पूर्ण: `erik_products.supplier_id` + `erik_orders.affiliate_link_id` (install.sql + ALTER); SettlementCron में आपूर्तिकर्ता मासिक सेटलमेंट (SupplierSettlements upsert) + वितरण कमीशन (AffiliateCommissions + AffiliateLinks गणना); **वास्तविक परीक्षण**: उत्पाद 99.98 आपूर्तिकर्ता के उसी माह सेटलमेंट में, 112.22@10% → वितरण कमीशन 11.22 और लिंक orders/commission अपडेट; AffiliateCommissions तालिका में updated_at नहीं → `$timestamps=false` जोड़ा |
| चरण दो P1: InstallController दोहरा-स्रोत तालिका सूची सत्यापन | ✅ | नया `scripts/check_install_tables.php` (install.sql तालिका नाम बनाम InstallController $tables_to_install, wa_ प्लगइन तालिकाएँ छूट), Makefile check में जोड़ा; **वास्तविक परीक्षण** 110 vs 110 समान OK |
| चरण दो P1: GDPR/CCPA कार्यान्वयन परत | ✅ | नया `PrivacyComplianceTask` (प्रति घंटा): data_delete ग्रेस अवधि के बाद उपयोगकर्ता अनामीकरण (email/email_hash/mobile साफ़, उपनाम "हटाए गए उपयोगकर्ता", status=0, कर फ़ील्ड रखे जाते हैं), data_access/data_portability निर्यात JSON जनन, opt_out चिह्न; नया `POST /api/privacy/cookie-consent` (CookieConsents लेखन, version/preferences JSON); **वास्तविक परीक्षण**: 31 दिन पुराना data_delete अनुरोध → उपयोगकर्ता अनामीकरण + अनुरोध completed; cookie-consent रिकॉर्ड पूर्ण |
| चरण दो P1: Klarna/Adyen दस्तावेज़ सुधार | ✅ | README.md (भुगतान पंक्ति/मूल मुद्रा कटौती/फ़ंक्शन तालिका) और docs/VERSIONS.md में Klarna/Adyen/BNPL को प्लेसहोल्डर चिह्नित किया, वास्तविक `PaymentGateway::make` throw के अनुरूप |
| चरण चार P2: स्टॉक लेज़र अपरिवर्तनीय बहीखाता | ✅ | `InventoryLogger` ऑर्डर कटौती (outbound)/रद्द पुनर्स्थापना (inbound) में जोड़ा गया, erik_inventory_logs लिखता है (balance_after स्नैपशॉट); **वास्तविक परीक्षण**: ऑर्डर -2/रद्द +2 लेज़र पूर्ण |
| चरण चार P2: वाणिज्यिक चालान/पैकिंग सूची PDF | ✅ | DocumentController पुनर्लेखन: dompdf आवश्यकतानुसार PDF जनन (विवरण + राशि + सीमा शुल्क घोषणा) public/documents/ + erik_order_documents (इडेमपोटेंट); पैरामीटर नाम और रूट {id} बेमेल सुधार; **वास्तविक परीक्षण**: दोनों PDF सफलतापूर्वक जनित |
| चरण तीन P1: admin गुणवत्ता गेट | ✅ | admin/phpunit.xml + tests/UtilTest.php (2/7 पास), phpstan.neon (level 5), .php-cs-fixer.php (fix हैंग सुधार), composer में phpstan, CI में admin चरण, Makefile test दोहरा प्रोजेक्ट |
| चरण चार P2: DB रीड/राइट स्प्लिटिंग | ✅ | 6 शुद्ध-क्वेरी मॉडल `$connection='mysql_rw'` पर सक्षम (Eloquent स्वचालित रीड/राइट विभाजन + sticky); **वास्तविक परीक्षण**: क्वेरी कनेक्शन=mysql_rw, लेखन सामान्य; उत्पादन में DB_READ_HOST_1/2 प्रभावी |
| चरण चार P2: सदस्यता चक्र खरीद API | ✅ | SubscriptionController (सदस्यता निर्माण + पहली अवधि ऑर्डर, मेरी सदस्यताएँ, रद्द); **वास्तविक परीक्षण**: निर्माण/सूची/रद्द सभी पास; SubscriptionOrders/Logs में `$timestamps=false` जोड़ा |
| चरण चार P2: बहु-प्लेटफ़ॉर्म लिस्टिंग लेखन | ✅ | `POST /api/admin/platform/listings` (AdminKeyMiddleware, PlatformListings draft/listed upsert); **वास्तविक परीक्षण**: लिस्टिंग रिकॉर्ड सफल लेखन |
| चरण चार P2: SubscriptionCron स्वचालित नवीनीकरण | ✅ | `service/app/process/SubscriptionCron.php` (दैनिक): समाप्त सदस्यताएँ → लेनदेन में नवीनीकरण ऑर्डर/चक्र संख्या +1 → next_billing अपडेट → लॉग; SKU हटा दिया/स्टॉक कम → paused; **वास्तविक परीक्षण**: स्मोक 7 असर्शन सभी पास |
| चरण चार P2: WS ग्राहक सेवा रीयल-टाइम IM | ✅ | `ChatController` (REST सत्र/संदेश) + `ChatWs` (WebSocket 8788, JWT + सत्र स्वामित्व प्रमाणीकरण, दोहरे चैनल समान-स्रोत लेखन); **वास्तविक परीक्षण**: एंड-टू-एंड 5 मद (हैंडशेक/ब्रॉडकास्ट/डेटाबेस/अमान्य token/अन्य सत्र अस्वीकृति); ज्ञात: कर्मचारी-पक्ष प्रमाणीकरण नहीं, सत्र बंद करने की क्रिया नहीं |
| चरण चार P2: ES बहुभाषी खोज | ✅ | webman-scout hosts `ELASTICSEARCH_HOST` env में बदले; Products `toSearchableArray()` बहुभाषी फ़ील्ड + `scripts/es-index-products.php` बैच इंडेक्सर; ES कॉन्फ़िगर न होने पर SQL डिग्रेडेशन; **वास्तविक परीक्षण**: डिग्रेडेशन पथ/डेटा आकार (कोई ES सेवा नहीं, ऑनलाइन क्वेरी परीक्षण नहीं) |
| चरण चार P2: Klarna/Adyen भुगतान स्केलेटन | ✅ | `KlarnaGateway/AdyenGateway` (Guzzle सीधा कनेक्शन: निर्माण/क्वेरी/रिफंड/Webhook HMAC हस्ताक्षर सत्यापन), कुंजी गायब होने पर env इंगित करने वाला अपवाद; `PaymentGatewayInterface` अलग किया; **वास्तविक परीक्षण**: हस्ताक्षर सत्यापन एल्गोरिदम दोनों दिशाओं + phpstan/phpunit सभी पास; वास्तविक कुंजियों के बिना उपयोग संभव नहीं |
| चरण चार P2: cron तीन URL env-करण | ✅ | `config/cron.php` तीन *_url env से पढ़ते हैं (TRACKING/COMPLIANCE/PLATFORM_URL); तीनों cron पुल लॉजिक पूर्ण; बाहरी वास्तविक API से नहीं जुड़े |
| चरण चार P2: हार्मनी KeyStore + क्लाइंट AES + भुगतान पूर्णता पृष्ठ | ✅ | हार्मनी `SecureStore.ets` (Asset Kit preferences के बजाय) + Flutter/हार्मनी `SecureCrypto.ets`/`_SecureCrypto` (AES-256-CBC, X-Encrypted/X-Encrypt-Response, कुंजी खाली होने पर प्लेनटेक्स्ट) + दोनों पक्षों के भुगतान पूर्णता पृष्ठ; **संकलन सत्यापन नहीं** (कोई टूलचेन नहीं), `flutter pub get`/hvigor संकलन लंबित |
| दस्तावेज़ समेकन | ✅ | README/VERSIONS/admin-CLAUDE.md में 8 अतिशयोक्तिपूर्ण घोषणाएँ सुधारीं (HS घोषणा→योजनाधीन, ऑर्डर निर्यात कॉलम वास्तविक के अनुसार, i18n स्विच बटन→योजनाधीन आदि); पैकिंग सूची/ट्रैकिंग की पुष्टि कार्यान्वित और संरक्षित; VERSIONS.md में 7 मद (AB परीक्षण/खरीद/गुणवत्ता जाँच/स्थानांतरण/बीमा/ज्ञानकोष/पॉइंट) "तालिका संरचना बनी" (◐) चिह्नित, कोड वास्तविकता के अनुरूप (केवल तालिका + मॉडल, कोई व्यावसायिक कोड नहीं) |
| दूसरा राउंड: JWT रद्दीकरण + पासवर्ड रीसेट + ईमेल सत्यापन | ✅ | Jwt में `revoke()`/`isRevoked()` (Redis ब्लैकलिस्ट), JwtAuth मिडलवेयर सत्यापन; AuthController logout/changePassword/passwordReset/emailVerify + रूट; install.sql में `email_verified_at` जोड़ा; JwtTest यूनिट टेस्ट पास |
| दूसरा राउंड: आंशिक रिफंड + webhook इवेंट पूर्ति | ✅ | RefundHelper आंशिक रिफंड राशि सत्यापन; AdminOpsController::executeRefund; PaymentController webhook इवेंट डिस्पैच (refunded/failed); RefundHelperTest पास |
| दूसरा राउंड: DevOps समेकन | ✅ | docker-compose पोर्ट समेकन 127.0.0.1, .dockerignore×2, .gitignore हार्मनी बिल्ड आर्टिफैक्ट, CI में Flutter/hvigor jobs, download-geoip.php स्क्रिप्ट |
| दूसरा राउंड: इंटीग्रेशन टेस्ट + admin P0 UI | ✅ | IntegrationTestCase (MySQL अनुपलब्धता स्किप + डिफ़ॉल्ट टेस्ट डेटाबेस हर केस में क्लियर) + OrderFlow/StripeWebhook/Hashids टेस्ट (phpunit 40 tests / 155 assertions सभी हरे); ShopOrder/ShopPayment मॉडल इनिशियलाइज़ेशन सुधार; admin ऑर्डर/भुगतान LayUI सूची पृष्ठ |
| 🔴 नई खोज बग: webhook विभाजन लेखन NOT NULL कॉलम से अवरुद्ध | ✅ | PaymentController::handlePaymentSucceeded के PlatformSettlements::create में supplier_amount/affiliate_amount गायब थे (schema NOT NULL बिना डिफ़ॉल्ट → webhook हमेशा 500); max(0, कुल-प्लेटफ़ॉर्म शुल्क-गेटवे शुल्क) गणना जोड़ी (SettlementCron के समान स्रोत); StripeWebhook इंटीग्रेशन टेस्ट 5/5 पास |
| तीसरा राउंड: रिफंड अनुरोध क्लोज्ड लूप | ✅ | RefundController (POST /api/refunds अनुरोध + सूची/विवरण, रिफंडेबल शेष=भुगतान-रिफंड-समीक्षाधीन) + AdminOps approve (0→3 परमाणु लैच + RefundHelper संबद्ध)/reject (0→2); Refunds status शब्दार्थ schema के अनुसार: 0 लंबित/2 अस्वीकृत/3 रिफंड; RefundFlow इंटीग्रेशन टेस्ट 3/34 |
| तीसरा राउंड: WS ग्राहक सेवा पूर्ति | ✅ | ChatWs कर्मचारी-पक्ष प्रमाणीकरण (पहला फ्रेम {type:'auth',role:'agent',key} + hash_equals स्थिर-समय तुलना, हैंडशेक pending भूमिका) + सत्र बंद (REST close/adminClose + WS close फ्रेम, closed REST 409/WS error रोकता है, closeSession इडेमपोटेंट + ब्रॉडकास्ट); ChatWs टेस्ट 5/21 |
| तीसरा राउंड: admin कोर प्रबंधन पृष्ठ | ✅ | उत्पाद/उपयोगकर्ता/रिफंड/कूपन/श्रेणी 5 पृष्ठ (LayUI order/payment के अनुरूप, सूची+पेजिनेशन+खोज+स्थिति फ़िल्टर+समीक्षा पॉपअप); Crud.php 3 मूल कारण सुधार (doFormat items() को Collection में लपेटना — ShopOrder/ShopReturn के समान गुप्त बग कवर, स्ट्रिंग मॉडल इंस्टेंटिएशन, दृश्य पथ अनुमान) + ShopProduct afterQuery स्टॉक एकत्रीकरण; ShopUserController नया |
| तीसरा राउंड: QA स्थिरीकरण | ✅ | SubscriptionCron (नवीनीकरण ऑर्डर/billing_cycle+1/next_billing विलंब/स्टॉक कम और हटाए गए SKU paused) + ES डिग्रेडेशन (SQL LIKE + SearchLogs रिकॉर्ड) टेस्ट; 🔴 नई खोज सुधार: SearchLogs में $timestamps=false गायब → खोज लॉग लेखन SQLSTATE 1054 500; पूरा सेट 54 tests / 256 assertions 0 विफलता |
| चौथा राउंड: इनपुट सीमा सुधार | ✅ | BaseApiController::clampPage (page≥1 / perPage∈[1,50]) 8 कंट्रोलर समेकित (Order/B2b/PriceAlert/Affiliate/Privacy/Notification/Return/Review, Search अलग fix-search से); AdminOps reason/remark ≤500 + createListing intval; json_decode खाली मान फ़ॉलबैक 5 स्थानों पर (SocialAuth×3/ExchangeRateCron/ComplianceCron); 4 वास्तविक अप्रयुक्त import हटाए (ऑडिट सूची के शेष 11 grep द्वारा उपयोग सिद्ध) |
| चौथा राउंड: खोज इंजेक्शन सुरक्षा | ✅ | SearchController: Lucene विशेष वर्ण preg_replace एस्केप (ES सिंटैक्स इंजेक्शन DoS रोकना) + keyword >64 → 422 + LIKE `%`/`_` addcslashes + per_page क्लैंप; 24 पंक्ति diff |
| चौथा राउंड: DevOps स्वच्छता | ✅ | admin composer.lock सिंक (phpstan सामिल) + service `--lock` रिफ्रेश; ci.yml audit "केवल CVE-2025-45769 जारी" मज़बूत संस्करण (एग्ज़िट कोड रखा, वास्तविक आउटपुट प्रारूप मिलान) + workflow_dispatch; autoload `""` खाली उपसर्ग ×2 हटाकर 5 स्पष्ट उपसर्ग जोड़े (dump-autoload सत्यापन); 35 Copyright हेडर पूरे; LICENSE proprietary घोषित (webman MIT मूल पाठ रखा); dockerignore में tests/docs जोड़े; compose प्लेसहोल्डर कुंजी गार्ड (production + change_me → exit 1, तीन शाखाएँ वास्तविक परीक्षण); **छोड़ा**: cs-fixer CI चरण (238/247 फ़ाइलें असंगत, पहले फ़ॉर्मेट कमिट आवश्यक) और admin audit (25 पूर्व-मौजूद चेतावनियाँ, निर्भरता अपग्रेड आवश्यक) |
| चौथा राउंड: दस्तावेज़/इंडेक्स संगति | ✅ | VERSIONS 7 मद ✅→◐ सुधार (वास्तविक परीक्षण: केवल तालिका+मॉडल) + आकार तालिका (Cron 11, टूल क्लास 15, टेस्ट 54/256); api.md में DELETE /api/comparisons/{id} जोड़ा; payment.php में adyen दर 2.99/0.30; install.sql में 6 इंडेक्स (refunds/return_orders idx_user_id, platform_listings idx_account_product, group_buys/flash_sales/coupons idx_status_time) + scripts/index-fixes.sql (निष्पादित नहीं, मौजूदा डेटाबेस के लिए); 🔴 टोडो: service/CLAUDE.md टूल क्लास 8→15, PHPUnit 22→54 गिनती पुरानी |
| चौथा राउंड: सुरक्षा सख्तीकरण | ✅ | BaseModel `$guarded=['id','money','score','level','created_at','updated_at']` (ऑडिट मूल सूची में user_id/status आदि 6 कॉलम थे, grep ने 40+ स्थानों पर create() बैच असाइनमेंट पुष्ट किया → सील करना डेटा भ्रष्टाचार होता, न्यूनतम विनाशकारी सूची के अनुसार कार्यान्वित); admin 5 पृष्ठों के table.render में `escape: true`; UploadController ब्लैकलिस्ट → 19 एक्सटेंशन श्वेतसूची; InstallController दोहरा सत्यापन (कॉन्फ़िगरेशन फ़ाइल + wa_options installed=1 चिह्न, DB अनुपलब्ध होने पर fail-closed); 🔴 पूर्व-मौजूद बग सूचना: product/index.html स्टॉक कॉलम templet में return नहीं → undefined दिखाता है |
| चौथा राउंड: परीक्षण सुदृढ़ीकरण | ✅ | SubscriptionController 4/33 (चक्र सत्यापन/अनधिकृत पहुँच/रद्द इडेमपोटेंसी) + Kyc 6/27 (Encryptable डिक्रिप्शन पुनर्स्थापना/अस्वीकृत पुनः सबमिट/पास पर सबमिट रोक) + RiskEngine 6/22 (अस्थायी ईमेल/बड़ी राशि/पता बेमेल/velocity/ip_reputation) इंटीग्रेशन टेस्ट; Kyc टेस्ट विधि नाम बदलकर PHPUnit 12 final status() ओवरराइड घातक त्रुटि से बचा; पूरा सेट 70 tests / 338 assertions 0 विफलता (1 पूर्व-मौजूद vendor चेतावनी: encryptable खाली IV) |
| पाँचवाँ राउंड: समवर्ती लॉक इन्फ्रास्ट्रक्चर | ✅ | नया app/common/DistributedLock.php (Redis SET NX EX स्पिन लॉक, Lua परमाणु रिलीज़ केवल स्व-धारित लॉक, fail-closed: Redis अपवाद पर बेपर्दा नहीं; एकल-नोड/वितरित एक ही पथ); webman/redis-queue v2.1.1 एकीकरण (db=2 prefix=erik_queue:, उपभोक्ता प्रक्रिया count=8, consumer_dir=app/queue/redis); घटक 5 सत्यापन स्क्रिप्ट सभी पास (दोहरी प्रक्रिया प्रतिस्पर्धा/टाइमआउट/गलत-डिलीट सुरक्षा) |
| पाँचवाँ राउंड: लेखन संचालन लॉकिंग | ✅ | ऑर्डर डुप्लिकेशन सुरक्षा lock:order:{userId} (OrderController store पूरे लेनदेन को लॉक, लॉक टाइमआउट 429/व्यावसायिक अपवाद 422); भुगतान इडेमपोटेंसी lock:payment:{orderId} (लॉक में लंबित भुगतान रिकॉर्ड मिलने पर तुरंत लौटना, डुप्लिकेट लंबित भुगतान रोकना); रिफंड अनुरोध lock:refund:{orderId} (लॉक में ऑर्डर + रिफंडेबल शेष पुनः जाँच, समवर्ती अतिरिक्त अनुरोध रोकना); सदस्यता store/cancel, पता is_default पहले-साफ़-फिर-सेट, सोशल लॉगिन बाइंडिंग, पसंदीदा, कार्ट रीड-राइट-अपडेट, समीक्षा (कोई यूनिक इंडेक्स नहीं, लॉक ही एकमात्र सुरक्षा), रजिस्ट्रेशन (email_hash NON-UNIQUE) — प्रत्येक परिदृश्य के अनुसार लॉक जोड़े; B2b मूल्य पूछताछ शुद्ध ऐपेंड, लॉक आवश्यक नहीं |
| पाँचवाँ राउंड: PDF जनन असिंक्रोनस | ✅ | DocumentController क्यू पुश कर तुरंत processing लौटता है; DocumentPdfConsumer (app/queue/redis/, क्यू document_pdf, payload order_id/type/user_id, उपभोग में मूल dompdf लॉजिक पूरी तरह स्थानांतरित, इडेमपोटेंट लेखन, विफलता पर लॉग बिना रीट्राई — उपयोगकर्ता दोबारा अनुरोध ही स्वाभाविक रीट्राई); स्थिति निर्धारण: रिकॉर्ड और फ़ाइल दोनों मौजूद = done, अन्यथा processing |
| शेष डिलीवरेबल | ⬜ | शेष: वास्तविक भुगतान SDK ऑनलाइन एकीकरण (कुंजियाँ आवश्यक), ES ऑनलाइन सत्यापन (कोई ES सेवा नहीं), Flutter/हार्मनी संकलन सत्यापन (कोई टूलचेन नहीं), हार्मनी सुरक्षित स्टोरेज वास्तविक-डिवाइस सत्यापन, cs-fixer फ़ॉर्मेट कमिट के बाद CI चरण, admin निर्भरता अपग्रेड के बाद audit चरण, PDF असिंक्रोनस एंड-टू-एंड सत्यापन (क्यू प्रक्रिया चलाना आवश्यक) |

---

## १,समग्र मूल्यांकन

Erik Shop का बुनियादी ढाँचा मज़बूत है (117 तालिकाएँ, 39 कंट्रोलर, Stripe/PayPal वास्तविक गेटवे, WAF/JWT/AES सुरक्षा स्टैक, 22 यूनिट टेस्ट सभी पास), लेकिन कोर ट्रेडिंग मुख्य श्रृंखला service/admin/Flutter/हार्मनी चारों पक्षों में एक साथ टूटी है, लगभग एक दर्जन दस्तावेज़ों द्वारा "पूर्ण" घोषित क्षमताएँ वास्तव में तालिका संरचना या CRUD स्टब हैं, और गुणवत्ता गेट (PHPStan/इंटीग्रेशन टेस्ट/क्लाइंट CI) नाममात्र मात्र है — समग्र रूप से **"स्केलेटन पूर्ण, क्लोज्ड लूप गायब, दस्तावेज़ आगे"** के चरण में है। 3-6 महीनों में पहले रक्तस्राव रोककर ट्रेडिंग क्लोज्ड लूप पूरा करना, फिर अनुपालन और गुणवत्ता आधार जोड़ना, अंत में वृद्धिशील क्षमताएँ बढ़ाकर दस्तावेज़ समेकित करना आवश्यक है।

## २,पाँच वैश्विक समस्याएँ

1. **कोर ट्रेडिंग मुख्य श्रृंखला तीनों पक्षों में एक साथ टूटी** (सर्वर/Admin/दोहरे क्लाइंट क्रॉस-पुष्टि): service पक्ष का `OrderController::store` कूपन/शिपिंग/सीमा शुल्क/जोखिम की गणना नहीं करता (केवल उत्पाद उप-योग जोड़ता है); Flutter और हार्मनी दोनों के ऑर्डर में `address_id` गायब और PosterVerify 40001 द्वारा अस्वीकृत, भुगतान कभी `POST /payment/create` कॉल नहीं करता; admin पक्ष के `ShopOrderController`/`ShopPaymentController` PHP 8.3 विधि सिग्नेचर असंगतता के कारण क्लास लोड होते ही Fatal। वर्तमान स्थिति में लॉन्च करने पर खरीद रूपांतरण पथ पूरी तरह अनुपलब्ध, ऑर्डर/भुगतान प्रबंधन मेनू खुलते ही क्रैश।
2. **दस्तावेज़ व्यवस्थित रूप से कोड से आगे** (दस्तावेज़/सर्वर/सुरक्षा/अनुपालन चारों क्षेत्रों से सुसंगत पुष्टि): `features.md`/`VERSIONS.md`/`README` जोखिम इंजन (RiskEngine), Klarna/Adyen भुगतान, चार-रेखा विभाजन, वाणिज्यिक चालान PDF, सदस्यता चक्र खरीद/AB परीक्षण, WebSocket ग्राहक सेवा IM, बहु-प्लेटफ़ॉर्म उत्पाद लिस्टिंग को सभी "पूर्ण/✅" चिह्नित करते हैं, वास्तविकता में केवल तालिका संरचना + admin CRUD या शून्य व्यावसायिक कार्यान्वयन — व्यावसायिक ग्राहकों के लिए डिलीवरी अपेक्षा और विश्वास जोखिम।
3. **व्यावसायिक सीड डेटा गायब + सुरक्षा/अनुपालन कार्यान्वयन परत खाली** (सर्वर/तैनाती/अनुपालन तीनों क्षेत्रों से साक्ष्य): `install.sql` में केवल सिस्टम तालिका सीड है, countries/currencies/payment_gateway_methods/hs_codes/shipping_zones नए इंस्टॉल के बाद पूरी तरह खाली (कोर इंटरफ़ेस बॉक्स से खाली लौटते हैं); साथ ही `blocked_countries` डिफ़ॉल्ट खाली सरणी, जोखिम शून्य कॉल, KYC में कोई सबमिट प्रवेश नहीं, GDPR/CCPA केवल रजिस्टर — "बॉक्स से खाली + डिफ़ॉल्ट जारी" के साथ अवास्तविक अनुपालन घोषणाएँ।
4. **Admin बैकएंड व्यावसायिक परत "कंट्रोलर हैं, पृष्ठ नहीं"**: 59/67 शुद्ध CRUD स्टब हैं, कोई HTML दृश्य नहीं, मेनू क्लिक पर 404; क्रॉस-बॉर्डर पैनल kpi/chartData रूट और json सिग्नेचर दोनों टूटे; 40 कंट्रोलर मेनू में नहीं, पूरा शॉपिंग-मॉल प्रबंधन UI वास्तव में अनुपलब्ध, दस्तावेज़ों द्वारा घोषित "पूर्ण प्रबंधन बैकएंड" से गंभीर रूप से असंगत।
5. **गुणवत्ता गेट नाममात्र मात्र** (परीक्षण/तैनाती/दस्तावेज़ तीनों क्षेत्रों से पुष्टि): केवल 22 यूनिट टेस्ट 4 टूल क्लास कवर करते हैं, व्यावसायिक कंट्रोलर/मिडलवेयर/मॉडल शून्य परीक्षण; PHPStan डिफ़ॉल्ट 128M बॉक्स से क्रैश, admin में कोई गुणवत्ता कॉन्फ़िगरेशन नहीं; CI में phpstan/php-cs-fixer/composer audit चरण नहीं, कोई Flutter/HarmonyOS job नहीं; हार्मनी के 99 बिल्ड आर्टिफैक्ट गलती से रिपॉजिटरी में, कोई भी रिफैक्टर/मर्ज बिना सुरक्षा के।

## ३,चरणबद्ध रोडमैप

### चरण एक: रक्तस्राव रोकना और ट्रेडिंग मुख्य श्रृंखला जोड़ना — **P0 · सप्ताह 1-4**

**लक्ष्य**
- admin के दो घातक कंट्रोलर सुधारना और पुनरावृत्ति-रोधी स्मोक तंत्र स्थापित करना, ऑर्डर/भुगतान प्रबंधन मेनू उपलब्धता बहाल करना
- service ऑर्डर वास्तविक बिलिंग (कूपन/शिपिंग/सीमा शुल्क/डिस्काउंट डेटाबेस में) + भुगतान इडेमपोटेंसी, बैकएंड ऑर्डर श्रृंखला क्लोज्ड लूप
- व्यावसायिक सीड डेटा स्वचालित आयात, नए इंस्टॉल पर कोर इंटरफ़ेस बॉक्स से डेटा युक्त
- Flutter और हार्मनी की चेकआउट-ऑर्डर-भुगतान श्रृंखला (address_id + PosterVerify + payment create/status)

**डिलीवरेबल**
- ✅ पूर्ण: `admin/plugin/admin/app/controller/shop/ShopOrderController.php` और `ShopPaymentController.php` में `: array`/`: Response` रिटर्न प्रकार जोड़े (82/82 रिफ्लेक्शन लोड पास); **शेष**: नया `scripts/smoke_controllers.php` (php -l + सभी 82 कंट्रोलरों का रिफ्लेक्शन लोड) और Makefile check + CI में जोड़ना, पुनरावृत्ति-रोधी गेट के रूप में
- 🔄 **समीक्षा नई (उच्च प्राथमिकता)**: PosterVerify जारी करने वाला इंटरफ़ेस `POST /api/poster/verify` — मिडलवेयर Redis कुंजी `erik:poster:{token}` सत्यापित करता है लेकिन पूरे प्रोजेक्ट में कोई जारी/लेखन कोड नहीं है, क्लाइंट X-Poster-Token प्राप्त नहीं कर सकता; poster-php से सत्यापन कोड जनन, Redis कुंजी लेखन (समाप्ति और एक-बार उपभोग सहित) आवश्यक — यह Flutter/हार्मनी रजिस्ट्रेशन, ऑर्डर, भुगतान मानव-सत्यापन एकीकरण की **पूर्व-निर्भरता** है
- `service/app/controller/v1/OrderController.php` store() में coupon छूट गणना और shipping_fee/tax_amount/discount_amount लेखन (api.md 5.3 / features.md 3.3 के अनुरूप), और api.md 2.1 के min_price/max_price फ़िल्टर कार्यान्वित; `PaymentController::create` में order_id+gateway इडेमपोटेंसी डिडुप्लिकेशन
- `admin/plugin/admin/app/controller/InstallController.php` step1 के अंत में `service/database/seeders/countries.php` निष्पादन जोड़ना, और erik_payment_gateway_methods (stripe/paypal प्रत्येक method पंक्ति), erik_hs_codes मूल लाइब्रेरी, erik_tariff_rules/erik_shipping_zones उदाहरण सीड
- `apps/flutter/lib/features/order/checkout_screen.dart` (**ध्यान दें: वास्तविक पथ, lib/screens/ नहीं**) में पता चयन और डिफ़ॉल्ट पता बैकफ़िल, address_id+currency_code सबमिट, PosterVerify (X-Poster-Token) एकीकरण के बाद `POST /payment/create` + `GET /payment/status` पोलिंग भुगतान पृष्ठ; `apps/harmonyos/entry/src/main/ets/pages/Checkout.ets` में address_id + selectedShipping + currency_code और भुगतान कॉल समान रूप से (हार्मनी को नया पता प्रबंधन पृष्ठ चाहिए, Profile में रिसीविंग एड्रेस रूट वर्तमान में खाली)
- ✅ पूर्ण: `ShopDashboardController.php` kpi/chartData रूट सुधार (kebab→क्लास-नाम सटीक मिलान) और `$this->json` सिग्नेचर विरोध, हार्डकोडेड उदाहरण डेटा बदला
- service ऑर्डर/भुगतान/रिफंड कोर इंटरफ़ेस के लिए इंटीग्रेशन टेस्ट (लेनदेन/स्टॉक कटौती/रद्द, webhook हस्ताक्षर सत्यापन+इडेमपोटेंसी+विभाजन, Hashids एन्कोड/डिकोड), CI में पहले से शुरू MySQL/Redis सेवाओं का पुनः उपयोग
- सहायक: `docs/deployment.md` में admin पोर्ट 8787→8788 के दो टाइपो सुधार

**जिम्मेदार भूमिकाएँ**: बैकएंड फुल-स्टैक, बैकएंड इंजीनियर, भुगतान सेटलमेंट, Flutter, हार्मनी, QA

### चरण दो: अनुपालन क्लोज्ड लूप और भुगतान सेटलमेंट विस्तार — **P1 · सप्ताह 5-10**

**लक्ष्य**
- जोखिम नियम इंजन लागू कर ऑर्डर स्टेट मशीन "समीक्षा लंबित (8)" से जोड़ना, "बिना जोखिम जाँच ऑर्डर जारी" का खुला जोखिम समाप्त करना
- KYC उपयोगकर्ता-पक्ष सबमिट क्लोज्ड लूप और GDPR/CCPA कार्यान्वयन परत (डिलीट/निर्यात/opt-out)
- समान विभाजन दर स्रोत और चार-रेखा विभाजन पूर्ति (Merchant/Supplier/Affiliate लेखन)
- भुगतान विधि घोषणा समेकन: Klarna/Adyen कार्यान्वित या स्पष्ट प्लेसहोल्डर + दस्तावेज़ समकालिक सुधार, 3DS स्पष्ट कोड

**डिलीवरेबल**
- नया `service/app/common/RiskEngine.php` (config/risk.php checks/velocity के अनुसार score), OrderController::store / PaymentController::create / AuthController में साइड-बाय स्कोरिंग, erik_orders.risk_score/risk_result और RiskLogs लेखन, उच्च स्कोर पर status=8; ShopRiskRule/ShopRiskLog admin मेनू में
- 🔄 **समीक्षा नई**: जोखिम समीक्षा निकास `POST /api/admin/orders/{id}/review` (AdminKeyMiddleware सुरक्षा, status=8 परमाणु लैच से 1 जारी/5 अस्वीकृत + OrderLogs लेखन) — वर्तमान में service पक्ष में status=8 लेखन/संक्रमण पथ बिल्कुल नहीं है, केवल मेनू लटकाने से "समीक्षा लंबित" मृत अंत है; admin पक्ष ShopOrder सूची में संबद्ध समीक्षा क्रिया
- `service/config/route.php` में `POST /api/kyc` और `GET /api/kyc/status` (real_name/id_number Encryptable के माध्यम से), admin समीक्षा पास पर status=1, OrderController के मौजूदा सत्यापन से जुड़ना (admin KYC समीक्षा प्रवेश स्पष्ट करें)
- नया `service/app/task/PrivacyComplianceTask` (config/privacy.php के अनुसार डेटा डिलीट ग्रेस अवधि/डेटा निर्यात फ़ाइल/opt_out ब्लॉक चिह्न) + `POST /api/privacy/cookie-consent` erik_cookie_consents लेखन
- webhook और SettlementCron को एकल दर कॉन्फ़िगरेशन स्रोत में मिलाना (gateway_fee दोहरे स्रोत विचलन समाप्त), MerchantSettlements/SupplierSettlements/AffiliateCommissions लेखन और भुगतान प्रक्रिया, docs/08-multi-currency-settlement का समर्थन
- **Klarna/Adyen डिफ़ॉल्ट क्रिया**: पहले "स्पष्ट throw प्लेसहोल्डर + api.md 6.1 / README / VERSIONS अभिव्यक्ति सुधार" (कम लागत, उसी दिन पूर्ण); पूर्ण कार्यान्वयन (सैंडबॉक्स भुगतान सफलता + webhook हस्ताक्षर सत्यापन + रिफंड स्वीकृति सहित) चरण चार में स्थगित; `StripeGateway::createPayment` में स्पष्ट `request_three_d_secure='automatic'` और erik_payments.three_ds_status वापस लेखन

**जिम्मेदार भूमिकाएँ**: सुरक्षा अनुपालन, भुगतान सेटलमेंट, बैकएंड इंजीनियर, बैकएंड फुल-स्टैक, क्रॉस-बॉर्डर i18n

### चरण तीन: गुणवत्ता गेट और बैकएंड UI पूर्ति — **P1/P2 · सप्ताह 11-18**

**लक्ष्य**
- स्थैतिक विश्लेषण गेट सुधार (PHPStan मेमोरी सीमा) और admin के लिए पूरा गुणवत्ता कॉन्फ़िगरेशन और परीक्षण स्केलेटन
- PHPUnit/phpstan/php-cs-fixer/composer audit/Flutter और हार्मनी CI सभी गेट में
- शॉपिंग-मॉल प्रबंधन P0 मॉड्यूल के लिए LayUI सूची पृष्ठ या 404 मेनू सफाई, "JSON API only" स्थिति स्पष्ट
- तैनाती और रनटाइम एक्सपोज़र सतह सुधार (पोर्ट बाइंडिंग, स्रोत वॉल्यूम माउंट, GeoIP डेटा, dev निर्भरताएँ)

**डिलीवरेबल**
- ✅ service पक्ष पूर्ण: phpstan कमांड `--memory-limit=1G` के साथ (Makefile/CI, PHPStan 2.x ने neon memoryLimit पैरामीटर हटा दिया); **शेष**: नया admin/phpstan.neon (level 5) + admin/.php-cs-fixer.php + admin/phpunit.xml + admin/tests/ (प्राथमिकता: Crud आधार क्लास inputFilter/doSelect/डेटा अधिकार, AccessControl प्रमाणीकरण, ShopRefundController मॉक रिमोट रिफंड)
- ✅ ci.yml में composer audit + phpstan नए चरण; **शेष**: php-cs-fixer --dry-run, service इंटीग्रेशन टेस्ट (MySQL/Redis सेवाएँ सीधा कनेक्शन), Flutter analyze+test job और हार्मनी hvigor बिल्ड job
- `admin/plugin/admin/app/controller/shop/` UI पूर्ति **प्राथमिकता मैट्रिक्स** के अनुसार: P0 (ऑर्डर/रिफंड/शिपिंग/भुगतान) के लिए index() और view/shop/ के अंतर्गत index.html (LayUI सूची) अनिवार्य; अन्य मेनू आइटम डिफ़ॉल्ट रूप से config/menu.php से हटाकर "JSON API only" चिह्नित (हटाना = 404 समाप्त, शून्य लागत), पृष्ठ जोड़ना बाद की आवश्यकता-आधारित वृद्धि के रूप में, अधूरे अर्ध-तैयार उत्पाद लटकाने से बचना
- 🔄 समीक्षा नई: हार्मनी रिपॉजिटरी प्रबंधन (.gitignore में `apps/harmonyos/**/build`, `**/.hvigor`, `**/oh_modules` और `git rm --cached` से 99 बिल्ड आर्टिफैक्ट सफाई; hvigorw wrapper जोड़ना) — यह CI में हार्मनी बिल्ड job जोड़ने की पूर्व-शर्त है
- 🔄 समीक्षा नई: install.sql और InstallController `$tables_to_install` संघर्ष तालिका सूची दोहरे-स्रोत रखरखाव सत्यापन स्क्रिप्ट (install.sql के CREATE TABLE पार्स करके गतिशील जनन या दोनों स्थानों की संगति तुलना)
- `docker-compose.yml` में ES/Redis/MySQL पोर्ट बाइंडिंग 127.0.0.1 (केवल nginx 80/443 एक्सपोज़), `./service:/app` और `./admin:/app` स्रोत वॉल्यूम माउंट हटाना और नए service/.dockerignore और admin/.dockerignore (vendor/runtime/.git बाहर), कंटेनर --no-dev vendor चलाना सुनिश्चित
- GeoLite2-Country.mmdb डाउनलोड स्क्रिप्ट (या MAXMIND_LICENSE_KEY स्वचालित अपडेट) service/database/geoip/ में; config/cron.php के तीन खाली URL लॉग WARNING स्तर और स्पष्ट टिप्पणी

**जिम्मेदार भूमिकाएँ**: QA, DevOps, बैकएंड फुल-स्टैक, Flutter, हार्मनी

### चरण चार: वृद्धिशील क्षमताएँ और दस्तावेज़ समेकन — **P2 · सप्ताह 19-26**

**लक्ष्य**
- दस्तावेज़ों में "पूर्ण" चिह्नित लेकिन वास्तव में गायब वृद्धिशील क्षमताएँ कार्यान्वित (चालान PDF, स्टॉक लेज़र, बहु-प्लेटफ़ॉर्म लिस्टिंग, सदस्यता चक्र खरीद)
- रीड/राइट स्प्लिटिंग, बहु-मुद्रा सेटलमेंट क्लोज्ड लूप और ES बहुभाषी खोज सुदृढ़ीकरण
- दस्तावेज़ तीन-स्थिति चिह्न एकीकरण (कार्यान्वित/तालिका संरचना बनी/योजनाधीन) और एंडपॉइंट संगति जाँच, आगे विचलन रोकना

**डिलीवरेबल**
- `service/app/controller/v1/DocumentController.php` पहले से शामिल barryvdh/laravel-dompdf से वाणिज्यिक चालान/पैकिंग सूची PDF आवश्यकतानुसार जनन और erik_order_documents लेखन; OrderController स्टॉक कटौती पर erik_inventory_logs अपरिवर्तनीय लेज़र
- PlatformOrderSyncCron में amazon/eBay/Shopee एडेप्टर और उत्पाद लिस्टिंग PlatformListings में लेखन; नया सदस्यता चक्र खरीद API (erik_subscriptions तालिका बनी है, पहले न्यूनतम व्यावसायिक दायरा परिभाषित करें: सदस्यता बिलिंग चक्र + रद्द + नवीनीकरण) और WebSocket ग्राहक सेवा सर्वर (ChatSessions/ChatMessages तालिकाएँ बनी हैं)
- config/database.php की mysql_rw रीड/राइट स्प्लिटिंग सक्षम (रीड-ओनली क्वेरी स्पष्ट स्विच, sticky शब्दार्थ सहित), CurrencyExchangeGainsLosses सेटलमेंट दर तुलना लेखन, बहु-मुद्रा विभाजन सेटलमेंट क्लोज्ड लूप
- `Products::toSearchableArray()` बहुभाषी title/description इंडेक्स फ़ील्ड और locale के अनुसार वेटिंग, ES बहुभाषी खोज सुदृढ़ीकरण
- Klarna/Adyen पूर्ण कार्यान्वयन (आवश्यकता-आधारित शेड्यूल, स्वीकृति शर्तें: सैंडबॉक्स भुगतान सफल + webhook हस्ताक्षर सत्यापन + रिफंड क्लोज्ड लूप)
- 🔄 समीक्षा नई: आंशिक रिफंड क्षमता (Refunds स्टेट मशीन 2/3 संक्रमण, आंशिक रिफंड राशि और ऑर्डर स्थिति संबद्ध) और webhook इवेंट कवरेज विस्तार (payment_intent.refunded/failed जैसे गैर-सफल इवेंट के लिए स्पष्ट हैंडलिंग नीति, वर्तमान में मौन अनदेखी PaymentReconcileCron फ़ॉलबैक पर निर्भर)
- 🔄 समीक्षा नई: प्रमाणीकरण सख्तीकरण — JWT रद्दीकरण (Redis ब्लैकलिस्ट या टोकन संस्करण संख्या, पासवर्ड बदलने/लॉगआउट के बाद अमान्य), पासवर्ड रीसेट/ईमेल सत्यापन प्रक्रिया (अनुसंधान §5 सुझाव, रोडमैप में पहले छूटा)
- ✅ समीक्षा नई: क्लाइंट AES इंटरफ़ेस एन्क्रिप्शन एकीकरण (Flutter/HarmonyOS X-Encrypted/X-Encrypt-Response समर्थन) + हार्मनी टोकन सुरक्षित स्टोरेज (KeyStore/security.asset preferences प्लेनटेक्स्ट के बजाय) — नीचे देखें「चरण चार P2: हार्मनी KeyStore + क्लाइंट AES + भुगतान पूर्णता पृष्ठ」(कोडित, संकलन सत्यापन लंबित)

**जिम्मेदार भूमिकाएँ**: बैकएंड इंजीनियर, बैकएंड फुल-स्टैक, भुगतान सेटलमेंट, क्रॉस-बॉर्डर i18n, QA

## ४,प्रमुख जोखिम (पहले अनिवार्य रूप से निपटें)

1. **भुगतान श्रृंखला में इडेमपोटेंसी की कमी और विभाजन दर दोहरा-स्रोत विचलन**: payment/create दोहराया अनुरोध कई लंबित भुगतान रिकॉर्ड बनाता है, webhook केवल सफल इवेंट संभालता है; gateway_fee दर दो स्थानों पर स्वतंत्र रखरखाव, विभाजन मापदंड में डुप्लिकेशन और असंगति जोखिम।
2. **दस्तावेज़ कोड से आगे का विश्वास जोखिम**: जोखिम इंजन, Klarna/Adyen, चार-रेखा विभाजन, चालान PDF, सदस्यता/AB, WS ग्राहक सेवा आदि दस से अधिक मद "पूर्ण" घोषित पर वास्तव में प्लेसहोल्डर या CRUD स्टब, व्यावसायिक ग्राहकों के लिए डिलीवरी अपेक्षा अंतर।
3. **नए इंस्टॉल पर सीड डेटा खाली + अनुपालन डिफ़ॉल्ट जारी**: countries/भुगतान विधियाँ/शिपिंग/सीमा शुल्क इंटरफ़ेस बॉक्स से खाली लौटते हैं; blocked_countries डिफ़ॉल्ट खाली सरणी, KYC केवल KR, गलत कॉन्फ़िगरेशन पर पूरी तरह खुला।
4. **गुणवत्ता गेट नाममात्र मात्र**: केवल 22 यूनिट टेस्ट टूल क्लास कवर करते हैं, PHPStan डिफ़ॉल्ट 128M बॉक्स से क्रैश, admin में परीक्षण और गुणवत्ता कॉन्फ़िगरेशन नहीं, CI में phpstan/composer audit/क्लाइंट job नहीं, रिफैक्टर मर्ज बिना सुरक्षा।
5. **उत्पादन मिडलवेयर एक्सपोज़र सतह**: ES बिना प्रमाणीकरण और 9200 एक्सपोज़्ड, Redis डिफ़ॉल्ट बिना पासवर्ड, MySQL/सेवा पोर्ट सभी एक्सपोज़्ड, .env अधूरा होने पर भी नंगे लॉन्च संभव।

## ५,Quick Wins (तुरंत करने योग्य कम-लागत उच्च-लाभ मामले)

1. **✅ पूर्ण** PHPStan गेट: Makefile check और CI के phpstan कमांड में `--memory-limit=1G` (ध्यान दें: PHPStan 2.2.8 ने neon का `memoryLimit` पैरामीटर हटा दिया है, CLI से पास करना अनिवार्य, neon में कॉन्फ़िगर करने पर `Unexpected item` त्रुटि)। वास्तविक परीक्षण `make check` → `[OK] No errors`।
2. **✅ पूर्ण** ShopOrderController/ShopPaymentController में `: array`/`: Response` रिटर्न प्रकार जोड़े, सुधार के बाद 82/82 कंट्रोलर रिफ्लेक्शन लोड सफल; पुनरावृत्ति-रोधी स्मोक स्क्रिप्ट चरण एक डिलीवरेबल देखें।
3. InstallController step1 के अंत में countries सीड और भुगतान विधियों/HS Code/शिपिंग सीमा शुल्क उदाहरण स्वचालित आयात, नया इंस्टॉल बॉक्स से डेटा युक्त।
4. **✅ पूर्ण** ShopDashboardController के kpi/chartData रूट सुधार (kebab→क्लास-नाम सटीक मिलान) और `$this->json` सिग्नेचर विरोध (बदला `$this->json(0,'ok',$data)`), हार्डकोडेड उदाहरण डेटा बदला।
5. **✅ पूर्ण** CI में composer audit चरण (`||` फ़ॉलबैक ज्ञात कम-जोखिम CVE से ब्लॉक नहीं होने देता) और phpstan चरण, निर्भरता सुरक्षा गेट में।

## ६,प्रारंभ क्रम सुझाव

**पहले चरण एक शुरू करें (रक्तस्राव रोकना और ट्रेडिंग मुख्य श्रृंखला जोड़ना)**: चारों पक्षों की ट्रेडिंग श्रृंखला टूटना और admin घातक त्रुटि लॉन्च-ब्लॉकिंग स्तर की समस्याएँ हैं; और कंट्रोलर सिग्नेचर सुधार, ऑर्डर बिलिंग, सीड आयात, दोहरे पक्ष का भुगतान जोड़ना एक-दूसरे से स्वतंत्र समानांतर किए जा सकते हैं, 1-4 सप्ताह में प्रभाव दिखता है; पहले मुख्य श्रृंखला चलाएँ, फिर बाद के अनुपालन और गुणवत्ता गेट के लिए सत्यापन योग्य आधार रेखा मिलती है।

## परिशिष्ट

- **टीम संरचना**: समन्वय परत (Team Lead, सिस्टम आर्किटेक्ट) → सर्वर टीम (बैकएंड/भुगतान सेटलमेंट/खोज अनुशंसा/बैकएंड फुल-स्टैक) → क्लाइंट टीम (Flutter, हार्मनी) → क्षैतिज समर्थन (सुरक्षा अनुपालन, QA, DevOps, क्रॉस-बॉर्डर i18n), विवरण के लिए रूट `../../CLAUDE.md` और टीम योजना चर्चा देखें।
- **अनुसंधान विवरण**: `PLAN-RESEARCH.md` (7 क्षेत्र: सर्वर API / प्रबंधन बैकएंड / Flutter / हार्मनी / सुरक्षा अनुपालन / तैनाती डेटा परीक्षण / दस्तावेज़ कार्यक्षमता कवरेज)।
