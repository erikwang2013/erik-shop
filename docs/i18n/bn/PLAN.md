# Erik Shop প্রজেক্ট প্ল্যান (টিম আউটপুট)

> এই নথিটি মূল চীনা ডকুমেন্টেশনের মেশিন অনুবাদ। মূল: [চীনা মূল](../../PLAN.md).

> **জেনারেশন সময়**: 2026-08
> **জেনারেশন পদ্ধতি**: মাল্টি-এজেন্ট টিম সহযোগিতা (৭টি ডোমেইন সমান্তরাল রিসার্চ → সিস্টেম আর্কিটেক্ট ইন্টিগ্রেশন → রিভিউ ইঞ্জিনিয়ার রিভেরিফিকেশন)
> **ভিত্তি**: `docs/PLAN-RESEARCH.md` (৭টি ডোমেইন রিসার্চ ডিটেইল), `README.md`, প্রতিটি সাব-প্রজেক্টের `CLAUDE.md`
> **প্রযোজ্য সময়কাল**: ৩-৬ মাস (৪টি পর্যায়)
> **রিভিউ রেকর্ড**: 2026-08 রিভিউ ইঞ্জিনিয়ার কোডের সাথে ১৮টি ক্লেইম রিভেরিফাই করেছে (১৬টি সঠিক, ২টি আংশিক সঠিক কারণ ওয়ার্কস্পেসে ইতিমধ্যে ফিক্স হয়েছে); এই ভার্সনে রিভিউ অ্যাডজাস্টমেন্ট অন্তর্ভুক্ত হয়েছে (PosterVerify ইস্যু ইন্টারফেস, রিস্ক রিভিউ আউটপুট, Flutter পাথ, ইমপ্লিমেন্টেশন স্ট্যাটাস মার্কিং ইত্যাদি)।

## ০. বর্তমান ইমপ্লিমেন্টেশন স্ট্যাটাস (রিভিউ সময়ে যাচাই)

> `git status`/`git diff` দিয়ে বাস্তব যাচাই; ✅=সম্পন্ন (ওয়ার্কস্পেসে আনকমিটেড), 🔄=চলছে, ⬜=শুরু হয়নি।

| আইটেম | স্ট্যাটাস | বিবরণ |
|---|---|---|
| admin-এর দুটি ফ্যাটাল কন্ট্রোলার সিগনেচার ফিক্স (ShopOrder/ShopPayment-এ `: array`/`: Response` যোগ) | ✅ | ফিক্সের পর 82/82 কন্ট্রোলার রিফ্লেকশন লোড সফল (ফিক্সের আগে 2টি Fatal) |
| PHPStan গেট | ✅ | `make check` বাস্তবে `[OK] No errors`; PHPStan 2.2.8-এ neon `memoryLimit` প্যারামিটার সরানো হয়েছে, Makefile/CI-তে `--memory-limit=1G` পাস করা হয় |
| ShopDashboardController json সিগনেচার + ভিউ fetch URL | ✅ | `$this->json(0,'ok',$data)` + `/ShopDashboard/kpi` ক্লাস-নেম রাউটিং |
| CI-তে composer audit + phpstan যোগ | ✅ | `.github/workflows/ci.yml`-এ দুই ধাপ নতুন যোগ (YAML ভ্যালিডেট করা হয়েছে) |
| `scripts/smoke_controllers.php` রিকারেন্স-প্রিভেনশন স্মোক | 🔄 | পর্যায় ১ ডেলিভারেবল দেখুন |
| PosterVerify ইস্যু ইন্টারফেস (`POST /api/poster/verify`) | ✅ | রিভিউতে নতুন পাওয়া + ইমপ্লিমেন্ট করা হয়েছে: `PosterController` (math অ্যারিথমেটিক প্রশ্ন) + রাউট; 8789 পোর্টে বাস্তবে ফুল চেইন পাস (challenge→verify→মিডলওয়্যার পাস→ওয়ান-টাইম কনজিউম) |
| 🔄 নতুন পাওয়া P0: Encryptable খালি IV রেজিস্ট্রেশন ব্লক | ✅ | ফিক্স হয়েছে: `app/common/SecureEncrypter.php` (স্পষ্ট 16 বাইট জিরো IV, পুরনো ডেটার সাথে বাইট-লেভেল কম্প্যাটিবল) + `support/bootstrap.php`-তে resolver রেজিস্টার; বাস্তবে রেজিস্ট্রেশন সফল, লগইন ডিক্রিপশন নরমাল |
| 🔄 নতুন পাওয়া P0: এনক্রিপ্টেড ফিল্ড কুয়েরি করা যায় না (email) | ✅ | ফিক্স হয়েছে: `erik_users.email_hash` (HMAC-SHA256 ইনডেক্স কলাম, install.sql + ALTER + ব্যাকফিল); AuthController register/login ও SocialAuthController email_hash দিয়ে কুয়েরি করে; বাস্তবে: রেজিস্ট্রেশন সফল/ডুপ্লিকেট রেজিস্ট্রেশন 422/লগইন সফল/ভুল পাসওয়ার্ড 401 |
| 🔄 নতুন পাওয়া P0: HASHIDS_SALT প্লেসহোল্ডার/পড়া হয়নি | ✅ | ফিক্স হয়েছে: `config/hashids.php` main.salt `getenv('HASHIDS_SALT')` পড়ে; এই এনভায়রনমেন্টে `.env`-এ র্যান্ডম salt জেনারেট হয়েছে (আগে change_me প্লেসহোল্ডার, আর কনফিগে খালি salt হার্ডকোড করা ছিল ফলে fail-closed এক্সেপশন) |
| Quick Win #3: বিজনেস সিড ডেটা অটো ইমপোর্ট | ✅ | নতুন `service/database/seeders/run.php` (আইডেমপোটেন্ট: countries 23 + logistics 3 + shipping zones 3 + rates 3 + gateways 2 + methods 2 + hs_codes 8 + tariff_rules 7); বাস্তবে রিরান করলে 0 নতুন 51 স্কিপ; /api/countries, /api/payment/methods, /api/shipping/calculate (উত্তর আমেরিকা জোন DHL 12.24), /api/tariff/estimate সব কাজ করে |
| 🔄 নতুন পাওয়া: মডেলে ভুল encryptable (name-এর মতো নন-সেন্সিটিভ ফিল্ড) | ✅ | 30+ মডেল name-এর মতো পাবলিক ফিল্ড এনক্রিপ্ট করত: নাম দিয়ে কুয়েরি/সর্ট ভাঙত আর ছোট ফিল্ডে সাইফারটেক্সট ধরা যেত না। সব ক্লিন করা হয়েছে: সিড সংশ্লিষ্ট 4 মডেল (আগের রাউন্ডে) + ব্যাচ 17 মডেল (Categories/Currencies/Shops/Suppliers.name/Merchants.store_name ইত্যাদি), email/mobile/real_name/api_key/access_token-এর মতো সত্যিকারের সেন্সিটিভ ফিল্ড রাখা হয়েছে |
| 🔄 নতুন পাওয়া: মডেলে Eloquent অ্যাসোসিয়েশন নেই | ✅ | PaymentGatewayMethods.gateway, ShippingZoneRates.logistics/zone-এর অভাব /api/payment/methods, /api/shipping/calculate-তে 500 দিত, যোগ করা হয়েছে |
| পর্যায় ১: OrderController প্রকৃত বিলিং | ✅ | store() কুপন (মিন-স্পেন্ড ডিসকাউন্ট/ডিসকাউন্ট/ফিক্সড, user_coupons + used_qty নিষ্ক্রিয়করণ), শিপিং ফি (জোন + রেট লেয়ারিং সর্বনিম্ন মূল্য), শুল্ক/VAT (HS Code→গন্তব্য দেশ ট্যাক্স রেট) যুক্ত; বাস্তবে 3×49.99=149.97, 100-এ 20 ছাড় → discount 20 + shipping 12.24 + tax 0 = pay 142.21, ইনভেন্টরি/নিষ্ক্রিয়করণ/ডিটেইল/লগ ফুল চেইন ভেরিফাইড |
| 🔄 নতুন পাওয়া P0: HashidsDecode প্যারামিটার লস | ✅ | মিডলওয়্যার setPost($updates) ছিল পুরো রিপ্লেস, কোনো _id ফিল্ড ডিকোড করলে একই রিকোয়েস্টের অন্য প্যারামিটার (coupon_id/weight_grams ইত্যাদি সাইটব্যাপী) হারিয়ে যেত; array_merge দিয়ে মার্জ করা হয়েছে, বাস্তবে মাল্টি-প্যারামিটার অর্ডার নরমাল |
| 🔄 নতুন পাওয়া: অর্ডার চেইনের সহগামী bug | ✅ | CouponController::claim-এর where কলাম নামের জায়গায় মান লেখা ছিল (whereColumn করা হয়েছে); Orders.address_snapshot JSON কলামে cast নেই (array cast যোগ); OrderLogs টেবিলে updated_at নেই (মডেলে $timestamps=false) |
| পর্যায় ১: InstallController-এ seeder ইন্টিগ্রেশন | ✅ | ইনস্টলেশন উইজার্ড install.sql ইমপোর্টের পর স্বয়ংক্রিয়ভাবে service/database/seeders/run.php চালায় (আলাদা সাব-প্রসেসে autoload আইসোলেশন, ব্যর্থ হলে শুধু সতর্কতা); সাথে install.sql পাথ bug ফিক্স (আগে base_path(false) admin/ দেখাত, রুটের install.sql পাওয়া যেত না, dirname করা হয়েছে) |
| পর্যায় ১: HarmonyOS অর্ডার/পেমেন্ট ইন্টিগ্রেশন | ✅ | Checkout.ets-এ ঠিকানা সিলেকশন, PosterVerify (challenge→verify), সম্পূর্ণ অর্ডার প্যারামিটার + X-Poster-Token, পেমেন্ট ইস্যু (payment/create) যুক্ত; ApiClient headers/প্যারামিটার এক্সটেন্ড; **hvigor assembleHap কম্পাইল পাস** |
| পর্যায় ১: Flutter অর্ডার/পেমেন্ট ইন্টিগ্রেশন | ⚠️ কোডেড, কম্পাইল ভেরিফিকেশন বাকি | checkout_screen-এ ঠিকানা/হিউম্যান ভেরিফিকেশন/সম্পূর্ণ অর্ডার/পেমেন্ট ইস্যু যুক্ত; register_screen-এ PosterVerify; api_client post headers সাপোর্ট করে। **এই এনভায়রনমেন্টে flutter SDK ক্যাশ রিড-অনলি, কম্পাইল করা যায় না**, লোকালি `flutter analyze`/`flutter test` দিয়ে ভেরিফাই করতে হবে (ব্র্যাকেট/স্ট্রাকচার স্ট্যাটিক চেক পাস করেছে) |
| পর্যায় ২ P1: রিস্ক ইঞ্জিন RiskEngine | ✅ | নতুন `app/common/RiskEngine.php` (email_domain টেম্প ইমেইল/velocity ফ্রিকোয়েন্সি/amount বড় অ্যামাউন্ট/address_mismatch/ip_reputation, Redis কাউন্টিং); অর্ডার/রেজিস্ট্রেশন/পেমেন্টে বাইপাস স্কোরিং + RiskLogs; **বাস্তবে**: টেম্প ইমেইল + বড় অর্ডার → 80 পয়েন্ট review → অর্ডার status=8 পর্যালোচনাধীন, risk_score/risk_result/OrderLog রিস্ক মার্কিং সব ঠিক |
| পর্যায় ২ P1: রিস্ক রিভিউ আউটপুট | ✅ | নতুন `POST /api/admin/orders/{id}/review` (AdminKeyMiddleware; approve→0 অনুমোদন/reject→5 প্রত্যাখ্যান, status=8 এটমিক ট্রানজিশন + OrderLogs); **বাস্তবে** approve/reject/ভুল key 403/ডুপ্লিকেট রিভিউ 422 সব সঠিক |
| পর্যায় ২ P1: KYC ইউজার-সাইড ক্লোজড লুপ | ✅ | নতুন KycController (POST /api/kyc সাবমিট + GET /api/kyc/status কুয়েরি, real_name/id_number Encryptable এনক্রিপ্ট, status 0 পর্যালোচনাধীন/1 অনুমোদিত/2 প্রত্যাখ্যাত); **বাস্তবে** সাবমিট/কুয়েরি নরমাল |
| পর্যায় ২ P1: পেমেন্ট হার্ডেনিং | ✅ | StripeGateway-তে স্পষ্ট `request_three_d_secure=automatic` (3DS); Klarna/Adyen `PaymentGateway::make` throw প্লেসহোল্ডার বজায় (ডকুমেন্টেশন ফিক্স বাকি) |
| 🔴 **নতুন পাওয়া গ্লোবাল bug: HashidsDecode রাউট প্যারামিটার ডিকোড কার্যকর হয় না** | ✅ | webman কন্ট্রোলার মেথড প্যারামিটার findRoute-এ ক্যাপচার করা অরিজিনাল hashid থেকে আসে (মিডলওয়্যার setParams কার্যকর হয় না)। ইউনিফাইড ফিক্স: `BaseApiController::decodedId()` হেল্পার + 17টি {id} রাউট মেথড এন্ট্রি পয়েন্টে যুক্ত (অর্ডার/প্রোডাক্ট/কার্ট/ঠিকানা/উইশলিস্ট/রিভিউ/পেমেন্ট স্ট্যাটাস/রিটার্ন/কুপন/নোটিফিকেশন/তুলনা/রিফান্ড এক্সিকিউশন/রিভিউ); **বাস্তবে** অর্ডার ডিটেইল, প্রোডাক্ট ডিটেইল, অর্ডার বাতিল, কার্ট update/destroy, কুপন ক্লেইম (hashid পাথ) সব পাস; সাথে Orders-এর items/logs/documents সম্পর্ক ও Carts-এর sku সম্পর্ক ফিক্স |
| পর্যায় ২ P1: ইউনিফাইড সেটেলমেন্ট রেট + সেলার সেটেলমেন্ট | ✅ | SettlementCron রেট সোর্স `payment.gateway_fee.{gateway}` + `payment.platform_rate`-এ ইউনিফাইড (webhook-এর সাথে একই সোর্স, cron.* শুধু কম্প্যাটিবিলিটি ফলব্যাক); নতুন MerchantSettlements রাইট (order_items→MerchantProducts approved→merchant.commission_rate); **বাস্তবে**: 162.21 অর্ডার → প্ল্যাটফর্ম 5% কমিশন 8.11 + stripe গেটওয়ে ফি 5.00, সেলার 149.97@8% → কমিশন 12.00 সেটেলমেন্ট 137.97 |
| পর্যায় ২ P1: চার-লাইন সেটেলমেন্ট পূর্ণতা (Supplier/Affiliate) | ✅ | schema পূর্ণতা: `erik_products.supplier_id` + `erik_orders.affiliate_link_id` (install.sql + ALTER); SettlementCron-এ সাপ্লায়ার পিরিয়ডিক সেটেলমেন্ট (মাসিক upsert SupplierSettlements) + অ্যাফিলিয়েট কমিশন (AffiliateCommissions + AffiliateLinks কাউন্টিং); **বাস্তবে**: 99.98 প্রোডাক্ট সাপ্লায়ারের মাসিক সেটেলমেন্টে, 112.22@10% → অ্যাফিলিয়েট কমিশন 11.22 এবং লিংক orders/commission আপডেট; AffiliateCommissions টেবিলে updated_at নেই, `$timestamps=false` যোগ |
| পর্যায় ২ P1: InstallController ডুয়াল-সোর্স টেবিল লিস্ট ভ্যালিডেশন | ✅ | নতুন `scripts/check_install_tables.php` (install.sql টেবিল নাম vs InstallController $tables_to_install পার্স, wa_ প্লাগইন টেবিল এক্সেম্প্ট), Makefile check-এ যুক্ত; **বাস্তবে** 110 vs 110 সামঞ্জস্যপূর্ণ OK |
| পর্যায় ২ P1: GDPR/CCPA এক্সিকিউশন লেয়ার | ✅ | নতুন `PrivacyComplianceTask` (প্রতি ঘণ্টা): data_delete গ্রেস পিরিয়ডের পর ইউজার অ্যানোনিমাইজ (email/email_hash/mobile খালি, নিকনেম "নিবন্ধন বাতিল ইউজার", status=0, ট্যাক্স ফিল্ড রাখা হয়), data_access/data_portability এক্সপোর্ট JSON জেনারেট, opt_out মার্ক; নতুন `POST /api/privacy/cookie-consent` (CookieConsents রাইট, version/preferences JSON); **বাস্তবে**: ৩১ দিন আগের data_delete রিকোয়েস্ট → ইউজার অ্যানোনিমাইজ + রিকোয়েস্ট completed; cookie-consent রেকর্ড সম্পূর্ণ |
| পর্যায় ২ P1: Klarna/Adyen ডকুমেন্টেশন ফিক্স | ✅ | README.md (পেমেন্ট লাইন/অরিজিনাল কারেন্সি চার্জ/ফিচার টেবিল) ও docs/VERSIONS.md-এ Klarna/Adyen/BNPL প্লেসহোল্ডার হিসেবে চিহ্নিত, প্রকৃত `PaymentGateway::make` throw-এর সাথে সামঞ্জস্যপূর্ণ |
| পর্যায় ৪ P2: ইনভেন্টরি লেজার ইমিউটেবল অ্যাকাউন্টিং | ✅ | `InventoryLogger` অর্ডার ডিডাকশন (outbound)/বাতিল রিস্টোর (inbound)-এ যুক্ত, erik_inventory_logs-এ রাইট (balance_after স্ন্যাপশট); **বাস্তবে** অর্ডার -2/বাতিল +2 লেজার সম্পূর্ণ |
| পর্যায় ৪ P2: কমার্শিয়াল ইনভয়েস/প্যাকিং লিস্ট PDF | ✅ | DocumentController রিরাইট: dompdf ডিমান্ড অনুযায়ী PDF জেনারেট (ডিটেইল + অ্যামাউন্ট + কাস্টমস ডিক্লারেশন) public/documents/ + erik_order_documents (আইডেমপোটেন্ট); প্যারামিটার নাম ও রাউট {id} মিল ফিক্স; **বাস্তবে** দুটি PDF জেনারেশন সফল |
| পর্যায় ৩ P1: admin কোয়ালিটি গেট | ✅ | admin/phpunit.xml + tests/UtilTest.php (2/7 পাস), phpstan.neon (level 5), .php-cs-fixer.php (fix হ্যাং ফিক্স), composer-এ phpstan, CI-তে admin ধাপ, Makefile test ডুয়াল প্রজেক্ট |
| পর্যায় ৪ P2: DB রিড-রাইট সেপারেশন | ✅ | 6টি পিওর-কুয়েরি মডেলে `$connection='mysql_rw'` (Eloquent অটো রিড-রাইট সেপারেশন + sticky); **বাস্তবে** কুয়েরি কানেকশন=mysql_rw, রাইট নরমাল; প্রোডাকশনে DB_READ_HOST_1/2 কার্যকর |
| পর্যায় ৪ P2: সাবস্ক্রিপশন পিরিয়ডিক পারচেজ API | ✅ | SubscriptionController (সাবস্ক্রিপশন তৈরি + প্রথম পিরিয়ড অর্ডার, আমার সাবস্ক্রিপশন, বাতিল); **বাস্তবে** তৈরি/লিস্ট/বাতিল সব পাস; SubscriptionOrders/Logs-এ `$timestamps=false` যোগ |
| পর্যায় ৪ P2: মাল্টি-প্ল্যাটফর্ম লিস্টিং রাইট | ✅ | `POST /api/admin/platform/listings` (AdminKeyMiddleware, PlatformListings draft/listed upsert); **বাস্তবে** লিস্টিং রেকর্ড রাইট সফল |
| পর্যায় ৪ P2: SubscriptionCron অটো রিনিউয়াল | ✅ | `service/app/process/SubscriptionCron.php` (দৈনিক): মেয়াদোত্তীর্ণ সাবস্ক্রিপশন→ট্রানজেকশনে রিনিউয়াল অর্ডার/সাইকেল+1→next_billing আপডেট→লগ; SKU অফলাইন/স্টক অপ্রতুল হলে paused; **বাস্তবে** স্মোক 7 অ্যাসারশন সব পাস |
| পর্যায় ৪ P2: WS কাস্টমার সার্ভিস রিয়েল-টাইম IM | ✅ | `ChatController` (REST সেশন/মেসেজ) + `ChatWs` (WebSocket 8788, JWT+সেশন ওনারশিপ অথেনটিকেশন, ডুয়াল-চ্যানেল সেম-সোর্স রাইট); **বাস্তবে** এন্ড-টু-এন্ড ৫টি আইটেম (হ্যান্ডশেক/ব্রডকাস্ট/DB রাইট/ইনভ্যালিড token/অন্য সেশনে অ্যাক্সেস অস্বীকার); জানা আছে: এজেন্ট-সাইড অথেনটিকেশন নেই, সেশন ক্লোজ অ্যাকশন করা হয়নি |
| পর্যায় ৪ P2: ES মাল্টি-ল্যাঙ্গুয়েজ সার্চ | ✅ | webman-scout hosts `ELASTICSEARCH_HOST` env দিয়ে; Products `toSearchableArray()` মাল্টি-ল্যাঙ্গুয়েজ ফিল্ড + `scripts/es-index-products.php` ব্যাচ ইনডেক্সার; ES কনফিগ না থাকলে SQL ডিগ্রেডেশন; **বাস্তবে** ডিগ্রেডেশন পাথ/ডেটা শেপ (ES সার্ভিস নেই, লাইভ কুয়েরি বাস্তবে পরীক্ষা হয়নি) |
| পর্যায় ৪ P2: Klarna/Adyen পেমেন্ট স্কেলটন | ✅ | `KlarnaGateway/AdyenGateway` (Guzzle ডাইরেক্ট: তৈরি/কুয়েরি/রিফান্ড/Webhook HMAC সিগনেচার ভেরিফিকেশন), কী না থাকলে এক্সেপশন থ্রো করে env নির্দেশ করে; `PaymentGatewayInterface` আলাদা করা হয়েছে; **বাস্তবে** সিগনেচার অ্যালগরিদম ডুয়াল-ডিরেকশন + phpstan/phpunit সব পাস; প্রকৃত কী লাগিয়ে দিলে তবেই কাজ করবে |
| পর্যায় ৪ P2: cron তিনটি URL env-করণ | ✅ | `config/cron.php`-এর তিনটি *_url env পড়ে (TRACKING/COMPLIANCE/PLATFORM_URL); তিনটি cron ফেচ লজিক সম্পূর্ণ; প্রকৃত বাহ্যিক API সংযোগ হয়নি |
| পর্যায় ৪ P2: HarmonyOS KeyStore + ক্লায়েন্ট AES + পেমেন্ট সম্পন্ন পেজ | ✅ | HarmonyOS `SecureStore.ets` (Asset Kit দিয়ে preferences-এর বদলে) + Flutter/HarmonyOS `SecureCrypto.ets`/`_SecureCrypto` (AES-256-CBC, X-Encrypted/X-Encrypt-Response, কী খালি থাকলে প্লেইনটেক্সট) + দুই প্ল্যাটফর্মে পেমেন্ট সম্পন্ন পেজ; **কম্পাইল ভেরিফিকেশন হয়নি** (টুলচেইন নেই), `flutter pub get`/hvigor কম্পাইলের জন্য অপেক্ষমাণ |
| ডকুমেন্টেশন কনভারজেন্স | ✅ | README/VERSIONS/admin-CLAUDE.md-এ ৮টি ওভার-ক্লেইম ফিক্স (HS ঘোষণা→পরিকল্পনাধীন, অর্ডার এক্সপোর্ট কলাম প্রকৃত অনুযায়ী, i18n সুইচ বাটন→পরিকল্পনাধীন ইত্যাদি); প্যাকিং লিস্ট/ট্র্যাকিং ট্র্যাকিং ইমপ্লিমেন্টেড হিসেবে রাখা হয়েছে; VERSIONS.md-এ ৭টি আইটেম (AB টেস্ট/প্রকিউরমেন্ট/কোয়ালিটি চেক/ট্রান্সফার/ইন্স্যুরেন্স/নলেজ বেস/পয়েন্ট) "টেবিল স্ট্রাকচার তৈরি হয়েছে" (◐) হিসেবে চিহ্নিত, কোডের বাস্তবতার সাথে সামঞ্জস্যপূর্ণ (শুধু টেবিল+মডেল, বিজনেস কোড নেই) |
| দ্বিতীয় রাউন্ড: JWT রিভোকেশন + পাসওয়ার্ড রিসেট + ইমেইল ভেরিফিকেশন | ✅ | Jwt-তে `revoke()`/`isRevoked()` (Redis ব্ল্যাকলিস্ট), JwtAuth মিডলওয়্যারে ভেরিফিকেশন; AuthController logout/changePassword/passwordReset/emailVerify + রাউট; install.sql-এ `email_verified_at`; JwtTest ইউনিট টেস্ট পাস |
| দ্বিতীয় রাউন্ড: আংশিক রিফান্ড + webhook ইভেন্ট পূর্ণতা | ✅ | RefundHelper আংশিক রিফান্ড অ্যামাউন্ট ভ্যালিডেশন সাপোর্ট; AdminOpsController::executeRefund; PaymentController webhook ইভেন্ট ডিসপ্যাচ (refunded/failed); RefundHelperTest পাস |
| দ্বিতীয় রাউন্ড: DevOps কনভারজেন্স | ✅ | docker-compose পোর্ট 127.0.0.1-এ কনভার্জ, .dockerignore×2, .gitignore-এ HarmonyOS বিল্ড আর্টিফ্যাক্ট, CI-তে Flutter/hvigor jobs, download-geoip.php স্ক্রিপ্ট |
| দ্বিতীয় রাউন্ড: ইন্টিগ্রেশন টেস্ট + admin P0 UI | ✅ | IntegrationTestCase (MySQL অনুপলব্ধ হলে স্কিপ + ডিফল্ট টেস্ট DB প্রতি টেস্ট কেসে ক্লিন) + OrderFlow/StripeWebhook/Hashids টেস্ট (phpunit 40 tests / 155 assertions সব সবুজ); ShopOrder/ShopPayment মডেল ইনিশিয়ালাইজেশন ফিক্স; admin অর্ডার/পেমেন্ট LayUI লিস্ট পেজ |
| 🔴 নতুন পাওয়া bug: webhook সেটেলমেন্ট রাইট NOT NULL কলামে ব্লকড | ✅ | PaymentController::handlePaymentSucceeded-এর PlatformSettlements::create-এ supplier_amount/affiliate_amount নেই (schema NOT NULL ডিফল্ট ছাড়া→webhook সবসময় 500); max(0, মোট-প্ল্যাটফর্ম ফি-গেটওয়ে ফি) গণনা যোগ (SettlementCron-এর সাথে একই সোর্স); StripeWebhook ইন্টিগ্রেশন টেস্ট 5/5 পাস |
| তৃতীয় রাউন্ড: রিফান্ড রিকোয়েস্ট ক্লোজড লুপ | ✅ | RefundController (POST /api/refunds রিকোয়েস্ট + তালিকা/ডিটেইল, রিফান্ডযোগ্য ব্যালেন্স=পরিশোধিত-রিফান্ডেড-রিভিউতে) + AdminOps approve (0→3 এটমিক গেট + RefundHelper লিংক)/reject (0→2); Refunds status সিম্যান্টিক্স schema অনুযায়ী: 0 পর্যালোচনাধীন/2 প্রত্যাখ্যাত/3 ফেরত দেওয়া হয়েছে; RefundFlow ইন্টিগ্রেশন টেস্ট 3/34 |
| তৃতীয় রাউন্ড: WS কাস্টমার সার্ভিস পূর্ণতা | ✅ | ChatWs এজেন্ট-সাইড অথেনটিকেশন (প্রথম ফ্রেম {type:'auth',role:'agent',key} + hash_equals কনস্ট্যান্ট-টাইম তুলনা, হ্যান্ডশেক পেন্ডিং রোল) + সেশন ক্লোজ (REST close/adminClose + WS close ফ্রেম, closed হলে REST 409/WS error, closeSession আইডেমপোটেন্ট + ব্রডকাস্ট); ChatWs টেস্ট 5/21 |
| তৃতীয় রাউন্ড: admin মূল ম্যানেজমেন্ট পেজ | ✅ | প্রোডাক্ট/ইউজার/রিফান্ড/কুপন/ক্যাটাগরি ৫ পেজ (LayUI order/payment-এর সাথে সামঞ্জস্য, লিস্ট+পেজিনেশন+সার্চ+স্ট্যাটাস ফিল্টার+রিভিউ মোডাল); Crud.php-তে ৩টি রুট-কারণ ফিক্স (doFormat items() Collection-এ প্যাক করে ShopOrder/ShopReturn-এর একই ল্যাটেন্ট bug কভার, string মডেল ইনস্ট্যানশিয়েশন, ভিউ পাথ ডিরাইভেশন) + ShopProduct afterQuery ইনভেন্টরি অ্যাগ্রিগেশন; ShopUserController নতুন |
| তৃতীয় রাউন্ড: QA ফিক্সেশন | ✅ | SubscriptionCron (রিনিউয়াল অর্ডার/billing_cycle+1/next_billing পিছানো/স্টক অপ্রতুল ও অফলাইন paused) + ES ডিগ্রেডেশন (SQL LIKE + SearchLogs রেকর্ড) টেস্ট; 🔴 নতুন পাওয়া ফিক্স: SearchLogs-এ $timestamps=false নেই → সার্চে লগ রাইটে SQLSTATE 1054 500; ফুল সেট 54 tests / 256 assertions 0 ব্যর্থ |
| চতুর্থ রাউন্ড: ইনপুট বাউন্ডারি ফিক্স | ✅ | BaseApiController::clampPage (page≥1 / perPage∈[1,50]) ইউনিফাইড 8 কন্ট্রোলারে (Order/B2b/PriceAlert/Affiliate/Privacy/Notification/Return/Review, Search fix-search দিয়ে আলাদা); AdminOps reason/remark ≤500 + createListing intval; json_decode খালি মান ফলব্যাক ৫ জায়গায় (SocialAuth×3/ExchangeRateCron/ComplianceCron); ৪টি সত্যিই অব্যবহৃত ইমপোর্ট মুছে ফেলা (অডিট তালিকার বাকি ১১টি grep দিয়ে ব্যবহৃত প্রমাণিত) |
| চতুর্থ রাউন্ড: সার্চ ইনজেকশন প্রোটেকশন | ✅ | SearchController: Lucene স্পেশাল ক্যারেক্টার preg_replace এস্কেপ (ES সিনট্যাক্স ইনজেকশন DoS প্রতিরোধ) + keyword >64 → 422 + LIKE `%`/`_` addcslashes + per_page ক্ল্যাম্প; 24 লাইন diff |
| চতুর্থ রাউন্ড: DevOps হাইজিন | ✅ | admin composer.lock সিঙ্ক (phpstan রেকর্ড) + service `--lock` রিফ্রেশ; ci.yml audit "শুধুমাত্র CVE-2025-45769 পাস" রোবাস্ট ভার্সন (এক্সিট কোড রাখা, বাস্তবে আউটপুট ফরম্যাট ম্যাচ) + workflow_dispatch; autoload `""` খালি প্রিফিক্স ×2 ডিলিট + ৫টি স্পষ্ট প্রিফিক্স যোগ (dump-autoload ভেরিফাইড); ৩৫টি Copyright হেডার পূর্ণ; LICENSE proprietary ঘোষণা (webman MIT মূল টেক্সট রাখা); dockerignore-এ tests/docs যোগ; compose প্লেসহোল্ডার কী গার্ড (production + change_me → exit 1, বাস্তবে তিন ব্রাঞ্চ); **স্কিপ**: cs-fixer CI ধাপ (238/247 ফাইল নন-কমপ্লায়েন্ট, আগে ফরম্যাট কমিট দরকার) ও admin audit (২৫টি প্রি-এক্সিস্টিং ওয়ার্নিং, ডিপেন্ডেন্সি আপগ্রেড দরকার) |
| চতুর্থ রাউন্ড: ডকুমেন্টেশন/ইনডেক্স কনসিসটেন্সি | ✅ | VERSIONS-এ ৭টি আইটেম ✅→◐ (বাস্তবে শুধু টেবিল+মডেল) + স্কেল টেবিল (Cron 11, টুল ক্লাস 15, টেস্ট 54/256); api.md-এ DELETE /api/comparisons/{id} যোগ; payment.php-এ adyen ফি 2.99/0.30 যোগ; install.sql-এ ৬টি ইনডেক্স যোগ (refunds/return_orders idx_user_id, platform_listings idx_account_product, group_buys/flash_sales/coupons idx_status_time) + scripts/index-fixes.sql (এক্সিকিউট করা হয়নি, এক্সিস্টিং DB-র জন্য); 🔴 টোডো: service/CLAUDE.md টুল ক্লাস 8→15, PHPUnit 22→54 কাউন্ট এক্সপায়ার্ড |
| চতুর্থ রাউন্ড: নিরাপত্তা হার্ডেনিং | ✅ | BaseModel `$guarded=['id','money','score','level','created_at','updated_at']` (অডিট মূল তালিকায় user_id/status ইত্যাদি ৬ কলাম ছিল, grep দেখিয়েছে 40+ জায়গায় create() ব্যাচ অ্যাসাইনমেন্ট→সব সিল করলে ডেটা ক্ষতি, সর্বনিম্ন ক্ষতিকর তালিকা অনুযায়ী); admin ৫ পেজ table.render-এ `escape: true`; UploadController ব্ল্যাকলিস্ট→১৯ প্রকার এক্সটেনশন হোয়াইটলিস্ট; InstallController ডুয়াল ভেরিফিকেশন (কনফিগ ফাইল + wa_options installed=1 মার্ক, DB অপ্রাপ্য fail-closed); 🔴 আরেকটি প্রি-এক্সিস্টিং bug: product/index.html ইনভেন্টরি কলাম templet-এ return নেই, undefined দেখায় |
| চতুর্থ রাউন্ড: টেস্ট শক্তিশালীকরণ | ✅ | SubscriptionController 4/33 (সাইকেল ভ্যালিডেশন/অননুমোদিত অ্যাক্সেস/বাতিল আইডেমপোটেন্সি) + Kyc 6/27 (Encryptable ডিক্রিপ্ট রিস্টোর/প্রত্যাখ্যাত পুনরায় সাবমিট/অনুমোদিত সাবমিট নিষিদ্ধ) + RiskEngine 6/22 (টেম্প ইমেইল/বড় অ্যামাউন্ট/ঠিকানা অমিল/velocity/ip_reputation) ইন্টিগ্রেশন টেস্ট; Kyc টেস্ট মেথড রিনেম করে PHPUnit 12 final status() ওভাররাইট ফ্যাটাল এড়ানো; ফুল সেট 70 tests / 338 assertions 0 ব্যর্থ (১টি প্রি-এক্সিস্টিং vendor warning: encryptable খালি IV) |
| পঞ্চম রাউন্ড: কনকারেন্সি লক ইনফ্রাস্ট্রাকচার | ✅ | নতুন app/common/DistributedLock.php (Redis SET NX EX স্পিন লক, Lua এটমিক রিলিজ শুধু নিজের লক ডিলিট করে, fail-closed: Redis এক্সেপশনে খালি হাতে ছাড়ে না; সিঙ্গেল-মেশিন/ডিস্ট্রিবিউটেড একই পথ); webman/redis-queue v2.1.1 ইন্টিগ্রেশন (db=2 prefix=erik_queue:, কনজিউম প্রসেস count=8, consumer_dir=app/queue/redis); কম্পোনেন্ট ৫-আইটেম ভেরিফিকেশন স্ক্রিপ্ট সব পাস (ডুয়াল-প্রসেস রেস/টাইমআউট/ভুল ডিলিট প্রতিরোধ) |
| পঞ্চম রাউন্ড: রাইট অপারেশনে লক | ✅ | অর্ডার ডুপ্লিকেট-প্রতিরোধ lock:order:{userId} (OrderController store ট্রানজেকশন পুরো লকে, লক টাইমআউট 429/বিজনেস এক্সেপশন 422); পেমেন্ট আইডেমপোটেন্সি lock:payment:{orderId} (লকের ভিতরে অপেক্ষমাণ পেমেন্ট রেকর্ড হিট হলে রিটার্ন, ডুপ্লিকেট অপেক্ষমাণ পেমেন্ট প্রতিরোধ); রিফান্ড রিকোয়েস্ট lock:refund:{orderId} (লকের ভিতরে অর্ডার + রিফান্ডযোগ্য ব্যালেন্স রিকুয়েরি, কনকারেন্ট ওভার-রিকোয়েস্ট প্রতিরোধ); সাবস্ক্রিপশন store/cancel, ঠিকানা is_default আগে ক্লিয়ার পরে সেট, সোশ্যাল লগইন বাইন্ডিং, উইশলিস্ট, কার্ট রিড-মডিফাই-রাইট, রিভিউ (ইউনিক ইনডেক্স নেই, লকই একমাত্র প্রতিরক্ষা), রেজিস্ট্রেশন (email_hash NON-UNIQUE) প্রতিটি পরিস্থিতিতে লক যোগ; B2b কোয়োট পিওর-অ্যাপেন্ড, লক প্রয়োজন নেই |
| পঞ্চম রাউন্ড: PDF জেনারেশন অ্যাসিঙ্ক-করণ | ✅ | DocumentController কুইনে পুশ করে সাথে সাথে processing রিটার্ন; DocumentPdfConsumer (app/queue/redis/, কুইন document_pdf, payload order_id/type/user_id, কনজিউমে পুরনো dompdf লজিক পুরো সরানো হয়েছে, আইডেমপোটেন্ট DB রাইট, ব্যর্থ হলে লগ করা হয় রিট্রাই না—ইউজার আবার রিকোয়েস্ট করলেই ন্যাচারাল রিট্রাই); স্ট্যাটাস জাজমেন্ট: রেকর্ড আছে ও ফাইল আছে=done, না হলে processing |
| বাকি ডেলিভারেবল | ⬜ | বাকি: প্রকৃত পেমেন্ট SDK লাইভ ইন্টিগ্রেশন (কী লাগবে), ES লাইভ ভেরিফিকেশন (ES সার্ভিস নেই), Flutter/HarmonyOS কম্পাইল ভেরিফিকেশন (টুলচেইন নেই), HarmonyOS সিকিউর স্টোরেজ রিয়েল-ডিভাইস ভেরিফিকেশন, cs-fixer ফরম্যাট কমিটের পর CI ধাপ, admin ডিপেন্ডেন্সি আপগ্রেডের পর audit ধাপ, PDF অ্যাসিঙ্ক এন্ড-টু-এন্ড ভেরিফিকেশন (কুইন প্রসেস চালানো লাগবে) |

---

## ১. সামগ্রিক মূল্যায়ন

Erik Shop ইনফ্রাস্ট্রাকচার স্কেলটন মজবুত (117 টেবিল, 39 কন্ট্রোলার, Stripe/PayPal প্রকৃত গেটওয়ে, WAF/JWT/AES সিকিউরিটি স্ট্যাক, 22 ইউনিট টেস্ট সব পাস), কিন্তু মূল ট্রানজেকশন মেইন চেইন service/admin/Flutter/HarmonyOS চার প্ল্যাটফর্মে একসাথে ভাঙা, প্রায় দশটি ডকুমেন্টে "সম্পূর্ণ" দাবি করা ক্ষমতা আসলে টেবিল স্ট্রাকচার বা CRUD স্টাব, কোয়ালিটি গেট (PHPStan/ইন্টিগ্রেশন টেস্ট/ক্লায়েন্ট CI) নামমাত্র — পুরোটা **"স্কেলটন সম্পূর্ণ, ক্লোজড লুপ নেই, ডকুমেন্টেশন এগিয়ে"** পর্যায়ে। ৩-৬ মাসের মধ্যে আগে রক্তক্ষরণ থামিয়ে ট্রানজেকশন ক্লোজড লুপ তৈরি করতে হবে, তারপর কমপ্লায়েন্স ও কোয়ালিটি বেস, শেষে ইনক্রিমেন্টাল ক্ষমতা বাড়িয়ে ডকুমেন্টেশন কনভার্জ করতে হবে।

## ২. পাঁচটি গ্লোবাল সমস্যা

1. **কোর ট্রানজেকশন মেইন চেইন তিন প্রান্তে একসাথে ভাঙা** (সার্ভিস/Admin/ডুয়াল ক্লায়েন্ট ক্রস-কনফার্মড): service-এ `OrderController::store` কুপন/শিপিং/শুল্ক/রিস্ক হিসাব করে না (শুধু প্রোডাক্ট সাবটোটাল যোগ); Flutter ও HarmonyOS অর্ডারে `address_id` নেই এবং PosterVerify 40001 রিজেক্ট করে, পেমেন্ট কখনো `POST /payment/create` কল করে না; admin-এ `ShopOrderController`/`ShopPaymentController` PHP 8.3 মেথড সিগনেচার সামঞ্জস্যহীনতায় ক্লাস লোডেই Fatal। বর্তমান অবস্থায় লাইভ দিলে কেনা-কনভার্সন পথ সম্পূর্ণ অকার্যকর, অর্ডার/পেমেন্ট ম্যানেজমেন্ট মেনু খুললেই ক্র্যাশ।
2. **ডকুমেন্টেশন সিস্টেমেটিকভাবে কোডের চেয়ে এগিয়ে** (ডকুমেন্ট/সার্ভিস/সিকিউরিটি/কমপ্লায়েন্স চার ডোমেইন একইভাবে নিশ্চিত): `features.md`/`VERSIONS.md`/`README`-এ রিস্ক ইঞ্জিন (RiskEngine), Klarna/Adyen পেমেন্ট, চার-লাইন সেটেলমেন্ট, কমার্শিয়াল ইনভয়েস PDF, সাবস্ক্রিপশন পিরিয়ডিক পারচেজ/AB টেস্ট, WebSocket কাস্টমার সার্ভিস IM, মাল্টি-প্ল্যাটফর্ম লিস্টিং সব "সম্পূর্ণ/✅" হিসেবে চিহ্নিত, বাস্তবে শুধু টেবিল স্ট্রাকচার + admin CRUD বা শূন্য বিজনেস ইমপ্লিমেন্টেশন — বাণিজ্যিক কাস্টমারদের কাছে ডেলিভারি প্রত্যাশা ও বিশ্বাস ঝুঁকি তৈরি করে।
3. **বিজনেস সিড ডেটার অভাব + সিকিউরিটি কমপ্লায়েন্স এক্সিকিউশন লেয়ার শূন্য** (সার্ভিস/ডিপ্লয়/কমপ্লায়েন্স তিন ডোমেইনে প্রমাণিত): `install.sql`-এ শুধু সিস্টেম টেবিল সিড, countries/currencies/payment_gateway_methods/hs_codes/shipping_zones নতুন ইনস্টলে পুরো খালি (কোর ইন্টারফেস বক্স-ওপেন খালি রিটার্ন); সাথে `blocked_countries` ডিফল্ট খালি অ্যারে, রিস্ক জিরো কল, KYC সাবমিট এন্ট্রি নেই, GDPR/CCPA শুধু নিবন্ধন এক্সিকিউশন না — "বক্স-ওপেন খালি + ডিফল্ট অনুমোদন" এর সাথে মিথ্যা কমপ্লায়েন্স ঘোষণা।
4. **Admin ব্যাকএন্ড বিজনেস লেয়ার "কন্ট্রোলার আছে পেজ নেই"**: 59/67 পিওর CRUD স্টাব, কোনো HTML ভিউ নেই, মেনু ক্লিক করলে 404; ক্রস-বর্ডার প্যানেল kpi/chartData রাউট ও json সিগনেচার ডাবল-ব্রোকেন; 40 কন্ট্রোলার মেনুতে লাগানো হয়নি, পুরো স্টোর ম্যানেজমেন্ট UI বাস্তবে অকার্যকর, ডকুমেন্টের "সম্পূর্ণ ম্যানেজমেন্ট ব্যাকএন্ড" দাবির সাথে গুরুতর অসামঞ্জস্য।
5. **কোয়ালিটি গেট নামমাত্র** (টেস্ট/ডিপ্লয়/ডকুমেন্ট তিন ডোমেইনে প্রমাণিত): মাত্র 22 ইউনিট টেস্ট ৪টি টুল ক্লাস কভার করে, বিজনেস কন্ট্রোলার/মিডলওয়্যার/মডেল জিরো টেস্ট; PHPStan ডিফল্ট 128M বক্স-ওপেন ক্র্যাশ, admin-এ কোনো কোয়ালিটি কনফিগ নেই; CI-তে phpstan/php-cs-fixer/composer audit ধাপ নেই, Flutter/HarmonyOS job নেই; HarmonyOS 99টি বিল্ড আর্টিফ্যাক্ট ভুলভাবে রিপোজিটরিতে, কোনো রিফ্যাক্টর মার্জ অরক্ষিত।

## ৩. পর্যায়ভিত্তিক রোডম্যাপ

### পর্যায় ১: রক্তক্ষরণ থামানো ও ট্রানজেকশন মেইন চেইন — **P0 · সপ্তাহ ১-৪**

**লক্ষ্য**
- admin-এর দুটি ফ্যাটাল কন্ট্রোলার ফিক্স + রিকারেন্স-প্রিভেনশন স্মোক মেকানিজম, অর্ডার/পেমেন্ট ম্যানেজমেন্ট মেনু ফিরিয়ে আনা
- service অর্ডারের প্রকৃত বিলিং (কুপন/শিপিং/শুল্ক/ডিসকাউন্ট DB-তে) + পেমেন্ট আইডেমপোটেন্সি, ব্যাকএন্ড অর্ডার চেইন ক্লোজড লুপ
- বিজনেস সিড ডেটা অটো ইমপোর্ট পূর্ণতা, নতুন ইনস্টলে কোর ইন্টারফেসে বক্স-ওপেন ডেটা নিশ্চিত
- Flutter ও HarmonyOS-এর চেকআউট-অর্ডার-পেমেন্ট চেইন (address_id + PosterVerify + payment create/status)

**ডেলিভারেবল**
- ✅ সম্পন্ন: `admin/plugin/admin/app/controller/shop/ShopOrderController.php` ও `ShopPaymentController.php`-এ `: array`/`: Response` রিটার্ন টাইপ (82/82 রিফ্লেকশন লোড পাস); **বাকি**: নতুন `scripts/smoke_controllers.php` (php -l + 82 কন্ট্রোলার রিফ্লেকশন লোড) + Makefile check ও CI-তে যুক্ত করা, রিকারেন্স-প্রিভেনশন গেট হিসেবে
- 🔄 **রিভিউ নতুন (উচ্চ প্রায়োরিটি)**: PosterVerify ইস্যু ইন্টারফেস `POST /api/poster/verify` —— মিডলওয়্যার Redis কী `erik:poster:{token}` ভেরিফাই করে কিন্তু পুরো প্রজেক্টে কোনো ইস্যু/রাইট-কী কোড নেই, ক্লায়েন্টের X-Poster-Token পাওয়ার উপায় নেই; poster-php দিয়ে ক্যাপচা জেনারেট, Redis কী রাইট (এক্সপায়ারি ও ওয়ান-টাইম কনজিউম সহ) করতে হবে, এটি Flutter/HarmonyOS রেজিস্ট্রেশন, অর্ডার, পেমেন্টে হিউম্যান ভেরিফিকেশন যুক্ত করার **প্রি-রিকুইজিট**
- `service/app/controller/v1/OrderController.php` store()-এ coupon ডিসকাউন্ট ক্যালকুলেশন ও shipping_fee/tax_amount/discount_amount DB-তে (api.md 5.3 / features.md 3.3-এর সাথে সামঞ্জস্য), এবং api.md 2.1-এর min_price/max_price ফিল্টার; `PaymentController::create`-এ order_id+gateway আইডেমপোটেন্সি ডিডুপ
- `admin/plugin/admin/app/controller/InstallController.php` step1 শেষে `service/database/seeders/countries.php` এক্সিকিউট, এবং erik_payment_gateway_methods (stripe/paypal প্রতিটি method রো), erik_hs_codes বেস লাইব্রেরি, erik_tariff_rules/erik_shipping_zones উদাহরণ সিড
- `apps/flutter/lib/features/order/checkout_screen.dart` (**নোট: প্রকৃত পাথ, lib/screens/ নয়**) ঠিকানা সিলেকশন ও ডিফল্ট ঠিকানা ব্যাকফিল, address_id+currency_code সাবমিট, PosterVerify (X-Poster-Token) ইন্টিগ্রেশন পরে `POST /payment/create` + `GET /payment/status` পোলিং পেমেন্ট পেজ; `apps/harmonyos/entry/src/main/ets/pages/Checkout.ets`-এ address_id + selectedShipping + currency_code ও পেমেন্ট কল সিঙ্ক (HarmonyOS-এ ঠিকানা ম্যানেজমেন্ট পেজ লাগবে, Profile ঠিকানা রাউট এখন খালি)
- ✅ সম্পন্ন: `ShopDashboardController.php` kpi/chartData রাউট ফিক্স (kebab→ক্লাস-নেম সুনির্দিষ্ট ম্যাচ) ও `$this->json` সিগনেচার কনফ্লিক্ট, হার্ডকোডেড উদাহরণ ডেটা রিপ্লেস
- service অর্ডার/পেমেন্ট/রিফান্ড কোর ইন্টারফেসে ইন্টিগ্রেশন টেস্ট (ট্রানজেকশন/স্টক ডিডাকশন/বাতিল, webhook সিগনেচার+আইডেমপোটেন্সি+সেটেলমেন্ট, Hashids এনকোড/ডিকোড), CI-তে থাকা MySQL/Redis সার্ভিস রিইউজ
- সহগামী: `docs/deployment.md`-এ admin পোর্ট 8787→8788 দুটি টাইপো ফিক্স

**দায়িত্বপূর্ণ রোল**: ব্যাকএন্ড ফুল-স্ট্যাক, ব্যাকএন্ড ইঞ্জিনিয়ার, পেমেন্ট সেটেলমেন্ট, Flutter, HarmonyOS, QA

### পর্যায় ২: কমপ্লায়েন্স ক্লোজড লুপ ও পেমেন্ট সেটেলমেন্ট এক্সটেনশন — **P1 · সপ্তাহ ৫-১০**

**লক্ষ্য**
- রিস্ক রুল ইঞ্জিন বাস্তবায়ন ও অর্ডার স্টেট মেশিনের "পর্যালোচনাধীন(8)"-এর সাথে সংযোগ, "অর্ডারে রিস্ক ছাড়াই পাস" ফাঁক দূর
- KYC ইউজার-সাইড সাবমিট ক্লোজড লুপ ও GDPR/CCPA এক্সিকিউশন লেয়ার (ডিলিট/এক্সপোর্ট/opt-out)
- ইউনিফাইড সেটেলমেন্ট রেট সোর্স ও চার-লাইন সেটেলমেন্ট পূর্ণতা (Merchant/Supplier/Affiliate রাইট)
- পেমেন্ট পদ্ধতি ঘোষণা কনভার্জ: Klarna/Adyen ইমপ্লিমেন্ট বা স্পষ্ট প্লেসহোল্ডার + ডকুমেন্টেশন সিঙ্ক, 3DS স্পষ্ট কোড

**ডেলিভারেবল**
- নতুন `service/app/common/RiskEngine.php` (config/risk.php checks/velocity অনুযায়ী score), OrderController::store / PaymentController::create / AuthController-এ বাইপাস স্কোরিং, erik_orders.risk_score/risk_result ও RiskLogs রাইট, উচ্চ স্কোরে status=8; ShopRiskRule/ShopRiskLog admin মেনুতে
- 🔄 **রিভিউ নতুন**: রিস্ক রিভিউ আউটপুট `POST /api/admin/orders/{id}/review` (AdminKeyMiddleware প্রোটেক্টেড, status=8 এটমিক গেটে 1 অনুমোদন/5 প্রত্যাখ্যান ও OrderLogs রাইট) —— বর্তমানে সার্ভিসে status=8 রাইট/ট্রানজিশন পাথ নেই, শুধু মেনু লাগালে "পর্যালোচনাধীন" মৃত পথই থেকে যাবে; admin সাইড ShopOrder লিস্টে রিভিউ অ্যাকশন
- `service/config/route.php`-তে `POST /api/kyc` ও `GET /api/kyc/status` (real_name/id_number Encryptable), admin রিভিউ পাসে status=1 সেট করে OrderController-এর বর্তমান ভ্যালিডেশনের সাথে সংযোগ (admin KYC রিভিউ এন্ট্রি স্পষ্ট করুন)
- নতুন `service/app/task/PrivacyComplianceTask` (config/privacy.php অনুযায়ী ডেটা ডিলিট গ্রেস পিরিয়ড/ডেটা এক্সপোর্ট ফাইল/opt_out ব্লক মার্ক) + `POST /api/privacy/cookie-consent` দিয়ে erik_cookie_consents রাইট
- webhook ও SettlementCron একক রেট কনফিগ সোর্সে মার্জ (gateway_fee ডুয়াল-সোর্স ড্রিফট দূর), MerchantSettlements/SupplierSettlements/AffiliateCommissions রাইট ও পেমেন্ট ফ্লো, docs/08-multi-currency-settlement সাপোর্ট
- **Klarna/Adyen ডিফল্ট অ্যাকশন**: আগে "স্পষ্ট throw প্লেসহোল্ডার + api.md 6.1 / README / VERSIONS সংশোধন" (কম খরচ, একদিনে); সম্পূর্ণ ইমপ্লিমেন্টেশন (স্যান্ডবক্স পেমেন্ট সফল + webhook সিগনেচার + রিফান্ড গ্রহণসহ) পর্যায় ৪-তে ডিগ্রেড; `StripeGateway::createPayment`-এ স্পষ্ট `request_three_d_secure='automatic'` ও erik_payments.three_ds_status ব্যাকরাইট

**দায়িত্বপূর্ণ রোল**: সিকিউরিটি কমপ্লায়েন্স, পেমেন্ট সেটেলমেন্ট, ব্যাকএন্ড ইঞ্জিনিয়ার, ব্যাকএন্ড ফুল-স্ট্যাক, ক্রস-বর্ডার i18n

### পর্যায় ৩: কোয়ালিটি গেট ও ব্যাকএন্ড UI পূর্ণতা — **P1/P2 · সপ্তাহ ১১-১৮**

**লক্ষ্য**
- স্ট্যাটিক অ্যানালাইসিস গেট ফিক্স (PHPStan মেমরি লিমিট) + admin-এর জন্য পূর্ণ কোয়ালিটি কনফিগ ও টেস্ট স্কেলটন
- PHPUnit/phpstan/php-cs-fixer/composer audit/Flutter ও HarmonyOS CI সব গেটে অন্তর্ভুক্ত
- স্টোর ম্যানেজমেন্ট P0 মডিউলের LayUI লিস্ট পেজ বা 404 মেনু ক্লিন, "JSON API only" পজিশনিং স্পষ্ট
- ডিপ্লয় ও রানটাইম এক্সপোজার সারফেস ফিক্স (পোর্ট বাইন্ডিং, সোর্স ভলিউম মাউন্ট, GeoIP ডেটা, dev ডিপেন্ডেন্সি)

**ডেলিভারেবল**
- ✅ service সাইড সম্পন্ন: phpstan কমান্ড `--memory-limit=1G` সহ (Makefile/CI, PHPStan 2.x-এ neon memoryLimit প্যারামিটার সরানো হয়েছে); **বাকি**: নতুন admin/phpstan.neon (level 5) + admin/.php-cs-fixer.php + admin/phpunit.xml + admin/tests/ (প্রথমে Crud বেস ক্লাস inputFilter/doSelect/ডেটা পারমিশন, AccessControl অথেনটিকেশন, ShopRefundController mock রিমোট রিফান্ড কভার)
- ✅ ci.yml-এ composer audit + phpstan সম্পন্ন; **বাকি**: php-cs-fixer --dry-run, service ইন্টিগ্রেশন টেস্ট (MySQL/Redis সার্ভিস ডাইরেক্ট), Flutter analyze+test job ও HarmonyOS hvigor বিল্ড job
- `admin/plugin/admin/app/controller/shop/` UI পূর্ণতা **প্রায়োরিটি ম্যাট্রিক্স** অনুযায়ী: P0 (অর্ডার/রিফান্ড/শিপমেন্ট/পেমেন্ট) index() ও view/shop/ index.html (LayUI লিস্ট) বাধ্যতামূলক; বাকি মেনু আইটেম ডিফল্টে config/menu.php থেকে সরিয়ে "JSON API only" চিহ্নিত (সরানো মানেই 404 দূর, জিরো খরচ), পেজ পরে ডিমান্ড অনুযায়ী ইনক্রিমেন্টাল, অসমাপ্ত অর্ধ-পণ্য এড়িয়ে
- 🔄 রিভিউ নতুন: HarmonyOS রিপোজিটরি গভর্ন্যান্স (.gitignore-এ `apps/harmonyos/**/build`, `**/.hvigor`, `**/oh_modules` + `git rm --cached` দিয়ে রেকর্ড করা 99টি বিল্ড আর্টিফ্যাক্ট ক্লিন; hvigorw wrapper যোগ) —— CI-তে HarmonyOS বিল্ড job যোগের প্রি-রিকুইজিট
- 🔄 রিভিউ নতুন: install.sql ও InstallController `$tables_to_install` কনফ্লিক্ট টেবিল তালিকা ডুয়াল-সোর্স মেইনটেন্যান্স ভেরিফিকেশন স্ক্রিপ্ট (install.sql-এর CREATE TABLE পার্স করে ডাইনামিক জেনারেট বা দুই জায়গা তুলনা)
- `docker-compose.yml`-এ ES/Redis/MySQL পোর্ট বাইন্ডিং 127.0.0.1 (শুধু nginx 80/443 এক্সপোজ), `./service:/app` ও `./admin:/app` সোর্স ভলিউম মাউন্ট সরানো + service/.dockerignore ও admin/.dockerignore নতুন (vendor/runtime/.git বাদ), কনটেইনার --no-dev vendor চলা নিশ্চিত
- GeoLite2-Country.mmdb ডাউনলোড স্ক্রিপ্ট (বা MAXMIND_LICENSE_KEY অটো আপডেট) service/database/geoip/ এ; config/cron.php তিনটি খালি URL লগ WARNING + স্পষ্ট কমেন্ট

**দায়িত্বপূর্ণ রোল**: QA, DevOps, ব্যাকএন্ড ফুল-স্ট্যাক, Flutter, HarmonyOS

### পর্যায় ৪: ইনক্রিমেন্টাল ক্ষমতা ও ডকুমেন্টেশন কনভার্জেন্স — **P2 · সপ্তাহ ১৯-২৬**

**লক্ষ্য**
- ডকুমেন্টে "সম্পূর্ণ" চিহ্নিত অথচ বাস্তবে অনুপস্থিত ইনক্রিমেন্টাল ক্ষমতা ইমপ্লিমেন্ট (ইনভয়েস PDF, ইনভেন্টরি লেজার, মাল্টি-প্ল্যাটফর্ম লিস্টিং, সাবস্ক্রিপশন পিরিয়ডিক পারচেজ)
- রিড-রাইট সেপারেশন, মাল্টি-কারেন্সি সেটেলমেন্ট ক্লোজড লুপ ও ES মাল্টি-ল্যাঙ্গুয়েজ সার্চ এনহ্যান্সমেন্ট সক্রিয়
- ডকুমেন্ট থ্রি-স্টেট মার্কিং ইউনিফাই (ইমপ্লিমেন্টেড/টেবিল স্ট্রাকচার তৈরি/পরিকল্পনায়) ও এন্ডপয়েন্ট কনসিসটেন্সি চেক, আরও ড্রিফট রোধ

**ডেলিভারেবল**
- `service/app/controller/v1/DocumentController.php`-এ ইতিমধ্যে যুক্ত barryvdh/laravel-dompdf দিয়ে ডিমান্ড অনুযায়ী কমার্শিয়াল ইনভয়েস/প্যাকিং লিস্ট PDF ও erik_order_documents রাইট; OrderController স্টক ডিডাকশনে erik_inventory_logs ইমিউটেবল লেজার রাইট
- PlatformOrderSyncCron-এ amazon/eBay/Shopee অ্যাডাপ্টার ও PlatformListings-এ প্রোডাক্ট লিস্টিং রাইট; নতুন সাবস্ক্রিপশন পিরিয়ডিক পারচেজ API (erik_subscriptions টেবিল আছে, আগে ন্যূনতম বিজনেস স্কোপ ডিফাইন: সাবস্ক্রিপশন বিলিং সাইকেল + বাতিল + রিনিউয়াল) ও WebSocket কাস্টমার সার্ভিস সার্ভার-সাইড (ChatSessions/ChatMessages টেবিল আছে)
- config/database.php-এর mysql_rw রিড-রাইট সেপারেশন সক্রিয় (রিড-অনলি কুয়েরি স্পষ্টভাবে স্যুইচ, sticky সেম্যান্টিকস সহ), CurrencyExchangeGainsLosses সেটেলমেন্ট রেট তুলনা রাইট, মাল্টি-কারেন্সি সেটেলমেন্ট ক্লোজড লুপ
- `Products::toSearchableArray()`-এ মাল্টি-ল্যাঙ্গুয়েজ title/description ইনডেক্স ফিল্ড ও locale অনুযায়ী ওয়েটিং, ES মাল্টি-ল্যাঙ্গুয়েজ সার্চ এনহ্যান্স
- Klarna/Adyen সম্পূর্ণ ইমপ্লিমেন্টেশন (ডিমান্ড অনুযায়ী শিডিউল, গ্রহণের শর্ত: স্যান্ডবক্স পেমেন্ট সফল + webhook সিগনেচার + রিফান্ড ক্লোজড লুপ)
- 🔄 রিভিউ নতুন: পেমেন্ট আংশিক রিফান্ড ক্ষমতা (Refunds স্টেট মেশিন 2/3 ট্রানজিশন, আংশিক রিফান্ড অ্যামাউন্ট ও অর্ডার স্ট্যাটাস লিংক) ও webhook ইভেন্ট কভারেজ এক্সটেনশন (payment_intent.refunded/failed ইত্যাদি নন-সাকসেস ইভেন্টের স্পষ্ট হ্যান্ডলিং স্ট্র্যাটেজি, বর্তমানে নীরবে ইগনোর করে PaymentReconcileCron ফলব্যাকের উপর নির্ভর)
- 🔄 রিভিউ নতুন: অথেনটিকেশন হার্ডেনিং——JWT রিভোকেশন (Redis ব্ল্যাকলিস্ট বা token ভার্সন নম্বর, পাসওয়ার্ড পরিবর্তন/লগআউটের পর অকার্যকর), পাসওয়ার্ড রিসেট/ইমেইল ভেরিফিকেশন ফ্লো (রিসার্চ §5 সুপারিশ, রোডম্যাপে আগে মিস হয়েছিল)
- ✅ রিভিউ নতুন: ক্লায়েন্ট AES ইন্টারফেস এনক্রিপশন (Flutter/HarmonyOS X-Encrypted/X-Encrypt-Response সাপোর্ট) + HarmonyOS token সিকিউর স্টোরেজ (KeyStore/security.asset দিয়ে preferences প্লেইনটেক্সটের বদলে) —— নিচের "পর্যায় ৪ P2: HarmonyOS KeyStore + ক্লায়েন্ট AES + পেমেন্ট সম্পন্ন পেজ" দেখুন (কোডেড, কম্পাইল ভেরিফিকেশন বাকি)

**দায়িত্বপূর্ণ রোল**: ব্যাকএন্ড ইঞ্জিনিয়ার, ব্যাকএন্ড ফুল-স্ট্যাক, পেমেন্ট সেটেলমেন্ট, ক্রস-বর্ডার i18n, QA

## ৪. মূল ঝুঁকি (অবশ্যই আগে মোকাবেলা)

1. **পেমেন্ট চেইনে আইডেমপোটেন্সি নেই ও সেটেলমেন্ট রেট ডুয়াল-সোর্স ড্রিফট**: payment/create বারবার রিকোয়েস্টে একাধিক অপেক্ষমাণ পেমেন্ট তৈরি হয়, webhook শুধু সফল ইভেন্ট প্রসেস করে; gateway_fee রেট দুই জায়গায় আলাদাভাবে মেইনটেইন, সেটেলমেন্ট হিসাবের ডুপ্লিকেশন ও অসামঞ্জস্য ঝুঁকি।
2. **ডকুমেন্টেশন কোডের চেয়ে এগিয়ে যাওয়ার বিশ্বাস ঝুঁকি**: রিস্ক ইঞ্জিন, Klarna/Adyen, চার-লাইন সেটেলমেন্ট, ইনভয়েস PDF, সাবস্ক্রিপশন/AB, WS কাস্টমার সার্ভিস ইত্যাদি দশাধিক আইটেম "সম্পূর্ণ" দাবি করলেও আসলে প্লেসহোল্ডার বা CRUD স্টাব, বাণিজ্যিক কাস্টমারদের কাছে ডেলিভারি প্রত্যাশা ফাঁক তৈরি করে।
3. **নতুন ইনস্টলে সিড ডেটা খালি + কমপ্লায়েন্স ডিফল্ট অনুমোদন**: countries/পেমেন্ট পদ্ধতি/শিপিং/শুল্ক ইন্টারফেস বক্স-ওপেন খালি রিটার্ন; blocked_countries ডিফল্ট খালি অ্যারে, KYC শুধু KR, মিস কনফিগ করলে সম্পূর্ণ খোলা।
4. **কোয়ালিটি গেট নামমাত্র**: মাত্র 22 ইউনিট টেস্ট টুল ক্লাস কভার করে, PHPStan ডিফল্ট 128M বক্স-ওপেন ক্র্যাশ, admin-এ টেস্ট ও কোয়ালিটি কনফিগ নেই, CI-তে phpstan/composer audit/ক্লায়েন্ট job নেই, রিফ্যাক্টর মার্জ অরক্ষিত।
5. **প্রোডাকশন মিডলওয়্যার এক্সপোজার সারফেস**: ES-এর অথেনটিকেশন নেই ও 9200 এক্সপোজড, Redis ডিফল্টে পাসওয়ার্ড নেই, MySQL/সার্ভিস পোর্ট সব এক্সপোজড, .env সম্পূর্ণ কনফিগ না করেই লাইভ চালু করা যায়।

## ৫. Quick Wins (সাথে সাথেই করা যায় এমন কম খরচের উচ্চ রিটার্ন বিষয়)

1. **✅ সম্পন্ন** PHPStan গেট: Makefile check ও CI-এর phpstan কমান্ড `--memory-limit=1G` সহ (নোট: PHPStan 2.2.8-এ neon-এর `memoryLimit` প্যারামিটার সরানো হয়েছে, অবশ্যই CLI দিয়ে পাস করতে হবে, neon-এ কনফিগ করলে `Unexpected item` এরর)। বাস্তবে `make check` → `[OK] No errors`।
2. **✅ সম্পন্ন** ShopOrderController/ShopPaymentController-এ `: array`/`: Response` রিটার্ন টাইপ, ফিক্সের পর 82/82 কন্ট্রোলার রিফ্লেকশন লোড সফল; রিকারেন্স-প্রিভেনশন স্মোক স্ক্রিপ্ট পর্যায় ১ ডেলিভারেবল দেখুন।
3. InstallController step1 শেষে countries সিড ও পেমেন্ট পদ্ধতি/HS Code/শিপিং শুল্ক উদাহরণ অটো ইমপোর্ট, নতুন ইনস্টলে বক্স-ওপেন ডেটা।
4. **✅ সম্পন্ন** ShopDashboardController-এর kpi/chartData রাউট (kebab→ক্লাস-নেম সুনির্দিষ্ট ম্যাচ) ও `$this->json` সিগনেচার কনফ্লিক্ট ফিক্স (`$this->json(0,'ok',$data)`), হার্ডকোডেড উদাহরণ ডেটা রিপ্লেস।
5. **✅ সম্পন্ন** CI-তে composer audit ধাপ ( `||` ফলব্যাক দিয়ে পরিচিত লো-রিস্ক CVE-তে ব্লক না করা) ও phpstan ধাপ, ডিপেন্ডেন্সি সিকিউরিটি গেটে।

## ৬. স্টার্ট অর্ডার সুপারিশ

**আগে পর্যায় ১ শুরু করুন (রক্তক্ষরণ থামানো ও ট্রানজেকশন মেইন চেইন)**: চার প্ল্যাটফর্মের ট্রানজেকশন চেইন ভাঙা ও admin ফ্যাটাল এরর লাইভ-ব্লকিং লেভেলের সমস্যা; আর কন্ট্রোলার সিগনেচার ফিক্স, অর্ডার বিলিং, সিড ইমপোর্ট, ডুয়াল-প্ল্যাটফর্ম পেমেন্ট চেইন পরস্পর স্বাধীন সমান্তরালভাবে করা যায়, ১-৪ সপ্তাহেই ফলাফল; প্রথমে মেইন চেইন চালু করলেই পরের কমপ্লায়েন্স ও কোয়ালিটি গেটের ভেরিফাইয়েবল বেসলাইন পাওয়া যাবে।

## অ্যাপেন্ডিক্স

- **টিম স্ট্রাকচার**: কোঅর্ডিনেশন লেয়ার (Team Lead, সিস্টেম আর্কিটেক্ট) → সার্ভিস-সাইড টিম (ব্যাকএন্ড/পেমেন্ট সেটেলমেন্ট/সার্চ রেকমেন্ডেশন/ব্যাকএন্ড ফুল-স্ট্যাক) → ক্লায়েন্ট-সাইড টিম (Flutter, HarmonyOS) → হরাইজন্টাল সাপোর্ট (সিকিউরিটি কমপ্লায়েন্স, QA, DevOps, ক্রস-বর্ডার i18n), বিস্তারিত রুট `CLAUDE.md` ও টিম প্ল্যানিং আলোচনায়।
- **রিসার্চ ডিটেইল**: `docs/PLAN-RESEARCH.md` (৭টি ডোমেইন: সার্ভিস-সাইড API / ম্যানেজমেন্ট ব্যাকএন্ড / Flutter / HarmonyOS / সিকিউরিটি কমপ্লায়েন্স / ডিপ্লয় ডেটা টেস্ট / ডকুমেন্টেশন ফিচার কভারেজ)।
