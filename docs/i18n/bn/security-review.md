# Security Plugin ইন্টিগ্রেশন রিভিউ রিপোর্ট

> এই নথিটি মূল চীনা ডকুমেন্টেশনের মেশিন অনুবাদ। মূল: [চীনা মূল](../../security-review.md).

**তারিখ**: 2026-08-04
**Scope**: erikwang2013/security-php v1.1.6 ইন্টিগ্রেশন
**রিভিউয়ার**: Claude Code (automated)

---

## 1. টেস্ট ফলাফল

| চেক | ফলাফল |
|---|---|
| PHP সিনট্যাক্স চেক (47 ফাইল) | সব পাস |
| PHPUnit (22 tests, 45 assertions) | সব পাস |
| SecurityGuard সিকিউরিটি পেলোড টেস্ট | XSS + SQLi সঠিকভাবে ব্লক করে |
| SecurityGuard সেফ রিকোয়েস্ট টেস্ট | কোনো মিথ্যা অ্যালার্ম নেই |
| phpstan স্ট্যাটিক অ্যানালাইসিস | ইনস্টল করা নেই (নন-ব্লকিং) |

## 2. ঠিক করা সমস্যাগুলি

### 2.1 ফাইল আপলোড ডেটা SecurityGuard-এ পাস করা হয়নি (Critical)

**ফাইল**: `service/app/middleware/SecurityMiddleware.php` + `admin/app/middleware/SecurityMiddleware.php`

মিডলওয়্যার শুধুমাত্র `$request->all()` `SecurityGuard::guard()`-এ পাস করত, কিন্তু এই মেথডে ফাইল আপলোড ডেটা অন্তর্ভুক্ত নয়। `UploadDetector`-এর `['tmp_name' => ..., 'name' => ...]` ফরম্যাটের ফাইল ডেটা প্রয়োজন।

**ফিক্স**: একটি লুপ যোগ করা হয়েছে, `$request->file()` ডেটা অ্যারের সাথে মার্জ করে `SecurityGuard::guard()`-এ পাস করা হয়।

### 2.2 Admin encryptable কনফিগে ডিফল্ট মান নেই (Medium)

**ফাইল**: `admin/config/plugin/erikwang2013/encryptable/app.php`

admin কনফিগ `env('ENCRYPTION_KEY')` ফলব্যাক মান ছাড়া ব্যবহার করে, এনভায়রনমেন্ট ভেরিয়েবল না থাকলে `null` রিটার্ন করে। Service `getenv('ENCRYPTION_KEY') ?: ''` ব্যবহার করে সঠিকভাবে খালি স্ট্রিংয়ে ফলব্যাক করে।

**ফিক্স**: admin কনফিগ `?: ''` অপারেটর ইউনিফাইডভাবে ব্যবহার করে, service-এর আচরণের সাথে সামঞ্জস্যপূর্ণ।

### 2.3 Docker Compose এনভায়রনমেন্ট ভেরিয়েবল অসম্পূর্ণ (Medium)

**ফাইল**: `docker-compose.yml`

- service কনটেইনারে `ENCRYPTION_CIPHER` এবং `ENCRYPTION_PREVIOUS_KEYS` নেই
- admin কনটেইনারে `ENCRYPTION_KEY`, `ENCRYPTION_CIPHER`, `ENCRYPTION_PREVIOUS_KEYS`, `HASHIDS_SALT`, `SNOWFLAKE_WORKER_ID`, `SNOWFLAKE_DATACENTER_ID` নেই

**ফিক্স**: সব অনুপস্থিত এনভায়রনমেন্ট ভেরিয়েবল যোগ করা হয়েছে, `.env.example`-এর সাথে সামঞ্জস্যপূর্ণ ডিফল্ট মান ব্যবহার করে।

### 2.4 WAF মিডলওয়্যার ডুপ্লিকেট ডিটেকশন (Critical, প্রথম রাউন্ডে ঠিক করা হয়েছে)

কাস্টম `SecurityMiddleware`-এ ~200 লাইন ইনলাইন রেজেক্স ছিল, `security-php` প্যাকেজের 31 ডিটেক্টরের সাথে সম্পূর্ণ ডুপ্লিকেট। প্রতিটি রিকোয়েস্ট দুইবার স্ক্যান হয়, CPU নষ্ট হয় ও ডাবল ব্লক সম্ভব।

**ফিক্স**: মিডলওয়্যার `SecurityGuard::guard()` API ব্যবহার করতে পুনরায় লেখা হয়েছে, 341 লাইন থেকে ~110 লাইনে (service), 136 লাইন থেকে ~85 লাইনে (admin) কমানো হয়েছে। ব্রুট-ফোর্স প্রতিরক্ষা ও রেসপন্স সিকিউরিটি হেডার সংরক্ষিত।

### 2.5 ENCRYPTION_KEY অনুপস্থিত (Critical, প্রথম রাউন্ডে ঠিক করা হয়েছে)

`.env.example` ফাইলে `ENCRYPTION_KEY` প্লেসহোল্ডার ব্যবহার করা হয়, `ENCRYPTION_CIPHER` এবং `ENCRYPTION_PREVIOUS_KEYS` নেই। কোনো প্রকৃত `.env` ফাইল নেই।

**ফিক্স**: 32 বাইট base64 কী জেনারেট করা হয়েছে, `ENCRYPTION_CIPHER=AES-256-CBC` এবং `ENCRYPTION_PREVIOUS_KEYS` যোগ করা হয়েছে, `.env` ফাইল তৈরি করা হয়েছে।

## 3. ইকোসিস্টেম কনফিগারেশন সম্পূর্ণতা

### 3.1 Packages (দুটি প্রজেক্টে সামঞ্জস্যপূর্ণ)

| Package | ভার্সন | Service | Admin |
|---|---|---|---|
| erikwang2013/security-php | v1.1.6 | ইনস্টল করা আছে | ইনস্টল করা আছে |
| erikwang2013/encryptable | - | ইনস্টল করা আছে | ইনস্টল করা আছে |
| erikwang2013/encryption | - | ইনস্টল করা আছে | ইনস্টল করা আছে |
| erikwang2013/jwt-webman | - | ইনস্টল করা আছে | ইনস্টল করা আছে |
| erikwang2013/hashids | - | ইনস্টল করা আছে | ইনস্টল করা আছে |
| erikwang2013/snowflake-php | - | ইনস্টল করা আছে | ইনস্টল করা আছে |
| erikwang2013/poster-php | - | ইনস্টল করা আছে | ইনস্টল করা আছে |
| erikwang2013/season | - | ইনস্টল করা আছে | ইনস্টল করা আছে |
| erikwang2013/webman-scout | - | ইনস্টল করা আছে | ইনস্টল করা আছে |

### 3.2 WAF কনফিগারেশন

| আইটেম | Service | Admin | স্ট্যাটাস |
|---|---|---|---|
| Config ফাইল | `config/plugin/erikwang2013/security-php/app.php` | একই | প্রকাশিত |
| ডিটেক্টর সক্রিয় | 31/31 | 31/31 | সঠিক |
| IP ব্ল্যাকলিস্ট | enabled (5 att/60s -> 900s ban) | একই | সঠিক |
| Block mode ডিটেক্টর | 28 | 28 | সঠিক |
| Log-only ডিটেক্টর | 3 (header_injection, ssti, nosql_injection) | 3 | সঠিক |
| স্টোরেজ | file | file | সঠিক |
| লগিং | enabled (file, 10MB rotate) | একই | সঠিক |
| মিডলওয়্যার রেজিস্টার্ড | `config/middleware.php` | `config/middleware.php` | সঠিক |

### 3.3 Encryption কনফিগারেশন

| আইটেম | Service | Admin | স্ট্যাটাস |
|---|---|---|---|
| ENCRYPTION_KEY | `base64:aJSrb...` | একই | সেট করা আছে |
| ENCRYPTION_CIPHER | `AES-256-CBC` | একই | সেট করা আছে |
| ENCRYPTION_PREVIOUS_KEYS | (empty) | (empty) | সেট করা আছে |
| encryptable config | `config/plugin/erikwang2013/encryptable/app.php` | একই (ইউনিফাইড) | সঠিক |
| encryption config | `config/encryption.php` | - | সঠিক |
| .env ফাইল | আছে | আছে | তৈরি করা হয়েছে |
| .env.example | আপডেট করা হয়েছে | আপডেট করা হয়েছে | সঠিক |
| docker-compose | আপডেট করা হয়েছে | আপডেট করা হয়েছে | সঠিক |

### 3.4 Encryptable Trait ব্যবহার করা Models

31 মডেল `Encryptable` trait ব্যবহার করে, সংবেদনশীল ফিল্ড সঠিকভাবে `$encryptable` হিসেবে ডিক্লেয়ার করা হয়েছে:

| ক্যাটাগরি | Models | সংবেদনশীল ফিল্ড |
|---|---|---|
| User PII | Users | email, mobile |
| User PII | UserAddresses | name, phone, detail |
| User PII | UserKyc | real_name, id_number |
| User PII | UserSocialAccounts | access_token, refresh_token |
| Privacy | PrivacyRequests | email |
| Finance | GiftCards | receiver_email |
| Finance | AffiliatePayouts | account |
| Finance | PaymentGateways | name, api_key, api_secret, webhook_secret |
| Platform | PlatformOrders | platform_account_id, buyer_name, buyer_email |
| Platform | PlatformAccounts | account_name, api_key, api_secret |
| Platform | PlatformListings | platform_account_id |
| Logistics | LogisticsCompanies | name, api_key |
| Supplier | Suppliers | name, email, phone |
| Supplier | B2bVerifications | company_name |
| Merchant | Merchants | store_name, email, phone |
| Other | EmailLogs | to_email |
| Other | আরও 15 মডেল | name ফিল্ড |

## 4. দ্বিতীয় রাউন্ডের ফিক্স (API এনক্রিপশন + JWT কী)

### 4.1 API রেসপন্স এনক্রিপশন মিডলওয়্যার (Medium, ঠিক করা হয়েছে)

**ফাইল**: `service/app/middleware/EncryptionMiddleware.php` (নতুন)

`erikwang2013/encryption` প্যাকেজ ইনস্টল করা আছে এবং `app/common/Encryption` টুল ক্লাস আছে, কিন্তু আগে মিডলওয়্যার পাইপলাইনে যুক্ত ছিল না। ইন্টারফেসের সংবেদনশীল ডেটায় ট্রান্সপোর্ট-লেভেল এনক্রিপশন/ডিক্রিপশন ছিল না।

**ফিক্স**:
- `EncryptionMiddleware` তৈরি করা হয়েছে, HTTP header ড্রাইভেন এনক্রিপশন/ডিক্রিপশন সহ:
  - `X-Encrypted: 1` — রিকোয়েস্ট ডিক্রিপশন: base64 সাইফারটেক্সট body ডিক্রিপ্ট করে JSON হিসেবে কন্ট্রোলারে পাঠায়
  - `X-Encrypt-Response: 1` — রেসপন্স এনক্রিপশন: রেসপন্সের `data` ফিল্ড base64 সাইফারটেক্সটে এনক্রিপ্ট করে
  - `X-Encrypt-Fields: field1,field2` — রেসপন্সের নির্দিষ্ট ফিল্ড শুধুমাত্র এনক্রিপ্ট করে
- মিডলওয়্যার স্ট্যাকের শেষ স্তরে রেজিস্টার করা হয়েছে (HashidsEncode-এর পরে)
- হেলথ চেক (`/api/health`, `/api/ping`) এবং ডকুমেন্টেশন এন্ডপয়েন্ট (`/apidoc`) এনক্রিপশন/ডিক্রিপশন স্কিপ করে

### 4.2 ক্লাস/ফাইলনেম অমিল (Medium, ঠিক করা হয়েছে)

**ফাইল**: `app/common/EncryptionHelper.php` → `app/common/Encryption.php`

ক্লাস `app\common\Encryption` ডিক্লেয়ার করা ছিল `EncryptionHelper.php` ফাইলে, যা PSR-4 স্ট্যান্ডার্ডের সাথে অমিল, ফলে Composer অটোলোড ব্যর্থ হয়। IDE ও CLI পরিবেশে ক্লাসটি autoloader দিয়ে খুঁজে পাওয়া যাচ্ছিল না।

**ফিক্স**: ফাইলটির নাম ক্লাস নামের সাথে মেলাতে `Encryption.php` করা হয়েছে।

### 4.3 JWT_SECRET_KEY খালি (Low, ঠিক করা হয়েছে)

**ফাইল**: `service/.env.example`, `service/.env`, `docker-compose.yml`

`JWT_SECRET_KEY` খালি স্ট্রিং, যদিও JWT মিডলওয়্যারে `JWT_SECRET → JWT_SECRET_KEY` ফলব্যাক চেইন আছে (প্রথমে `JWT_SECRET` ব্যবহার করে), কিন্তু প্লেসহোল্ডার মান নিরাপদ নয়।

**ফিক্স**: 32 বাইট base64 কী জেনারেট করা হয়েছে, `JWT_SECRET` এবং `JWT_SECRET_KEY` দুটোই সেট করা হয়েছে। `.env.example`, `.env` এবং `docker-compose.yml` আপডেট করা হয়েছে।

## 5. পর্যবেক্ষণযোগ্য সমস্যা (সম্ভাব্য অপটিমাইজেশন পয়েন্ট)

### 5.1 SecurityGuard-এর webman/Workerman header ডিপেন্ডেন্সি (Low Risk)

**প্রভাব**: CSRF Origin, Host Header, DNS Rebinding, Request Smuggling, CORS ইত্যাদি ডিটেক্টর `$_SERVER`-এর HTTP হেডার ডেটার উপর নির্ভর করে।

Workerman নন-CGI পরিবেশে `$_SERVER` সম্পূর্ণভাবে HTTP হেডার দিয়ে পূর্ণ নাও হতে পারে। SecurityGuard-এর ফলব্যাক লজিক আছে (header খালি হলে ডিটেকশন স্কিপ করে), তাই **মিথ্যা অ্যালার্ম হবে না**, তবে **কিছু header অ্যাটাক মিস হতে পারে**। প্রভাব কম, কারণ Nginx রিভার্স প্রক্সি স্তরে সাধারণত ম্যালিশিয়াস header ফিল্টার করা হয়।

**সুপারিশ**: আরও সম্পূর্ণ header ডিটেকশন দরকার হলে SecurityGuard-এর `$meta` প্যারামিটারে স্পষ্টভাবে header মান পাস করা যেতে পারে। বর্তমানে পরিবর্তনের প্রয়োজন নেই।

### 5.2 CSRF Origin ডিটেক্টরের Admin-এ প্রভাব (No Risk)

Admin-এর `csrf_origin` ডিটেক্টর `block` মোডে, `allowed_origins` খালি। কিন্তু ডিটেক্টর শুধুমাত্র Origin header থাকলে এবং Host-এর সাথে মিল না হলে ট্রিগার হয়, ম্যানেজমেন্ট ব্যাকএন্ড অ্যাক্সেসে সাধারণত Origin header থাকে না (সেম-অরিজিন অ্যাক্সেস), তাই **মিথ্যা ব্লক হবে না**।

### 5.3 31 ডিটেক্টর সব সক্রিয়, প্রতি রিকোয়েস্ট ওভারহেড (Performance Note)

সব রিকোয়েস্টে 31টি ডিটেক্টরই চলে (JWT, WebSocket, GraphQL, CSV, prototype pollution সহ)। প্রতিটি ডিটেক্টর রিকোয়েস্টের সব ফিল্ডে রেজেক্স ম্যাচ করে। এই প্রজেক্টের ব্যবহার পরিস্থিতিতে ওভারহেড গ্রহণযোগ্য (webman মেমরিতে স্থায়ী প্রসেস, CGI কোল্ড স্টার্ট ওভারহেড নেই)।

### 5.4 IP ব্ল্যাকলিস্ট পারসিস্টেন্স (Operational Note)

স্টোরেজ ব্যাকএন্ড `file` মোড, ডিফল্ট পাথ `sys_get_temp_dir() . '/security_storage.json'`। Docker কনটেইনারে রিস্টার্টের পর টেম্প ডিরেক্টরি হারিয়ে যেতে পারে। মাল্টি-কনটেইনার ডিপ্লয়মেন্টে ব্ল্যাকলিস্ট শেয়ার করতে হলে `redis` মোডে স্যুইচ করা যেতে পারে।

## 6. পরিবর্তিত ফাইল সামারি

```
admin/.env.example                                (ENCRYPTION_KEY নতুন যোগ)
admin/.env                                        (.env.example থেকে তৈরি)
admin/CLAUDE.md                                   (মিডলওয়্যার স্ট্যাক + tech stack আপডেট)
admin/composer.json                               (security-php ডিপেন্ডেন্সি)
admin/config/plugin/erikwang2013/encryptable/app.php  (ডিফল্ট মান ইউনিফাইড)
admin/config/plugin/erikwang2013/security-php/app.php  (নতুন, 31 ডিটেক্টর)
admin/app/middleware/SecurityMiddleware.php       (SecurityGuard ব্যবহারে পুনরায় লেখা)
service/.env.example                              (ENCRYPTION_KEY/CIPHER + JWT কী আপডেট)
service/.env                                      (.env.example থেকে তৈরি, JWT কী সিঙ্ক)
service/CLAUDE.md                                 (মিডলওয়্যার স্ট্যাক + Encryption + tech stack আপডেট)
service/composer.json                             (security-php ডিপেন্ডেন্সি)
service/config/middleware.php                     (+ EncryptionMiddleware)
service/config/plugin/erikwang2013/security-php/app.php  (নতুন, 31 ডিটেক্টর)
service/app/common/Encryption.php                 (EncryptionHelper.php থেকে রিনেম)
service/app/middleware/EncryptionMiddleware.php   (নতুন, API রেসপন্স এনক্রিপশন/ডিক্রিপশন)
service/app/middleware/SecurityMiddleware.php     (SecurityGuard + ফাইল আপলোড ব্যবহারে পুনরায় লেখা)
docker-compose.yml                                (encryption/jwt এনভায়রনমেন্ট ভেরিয়েবল পূর্ণ)
docs/security-review.md                           (এই রিপোর্ট)
```

## 7. উপসংহার

**স্ট্যাটাস**: পাস

- WAF ডিটেকশন XSS, SQL ইনজেকশন ইত্যাদি অ্যাটাক সঠিকভাবে ব্লক করে (31 ডিটেক্টর, SecurityGuard::guard API)
- সংবেদনশীল ফিল্ড এনক্রিপশন কনফিগ সম্পূর্ণ (31 মডেল, 6 ধরনের সংবেদনশীল ডেটা, Encryptable trait)
- API ট্রান্সপোর্ট এনক্রিপশন/ডিক্রিপশন মিডলওয়্যারে যুক্ত হয়েছে (EncryptionMiddleware, AES-256-CBC, header ট্রিগার)
- JWT কী কনফিগার করা হয়েছে (JWT_SECRET + JWT_SECRET_KEY দুটোই সেট)
- ফাইল আপলোড ডিটেকশন ঠিক করা হয়েছে ($_FILES ডেটা মার্জ করে SecurityGuard-এ পাস)
- কোনো ফাংশনাল রিগ্রেশন নেই (22/22 টেস্ট পাস)
- কোনো মিডলওয়্যার ডুপ্লিকেট ডিটেকশন নেই
- Docker ডিপ্লয়মেন্ট এনভায়রনমেন্ট ভেরিয়েবল সম্পূর্ণ
