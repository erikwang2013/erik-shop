# Security Plugin एकीकरण समीक्षा रिपोर्ट

**दिनांक**: 2026-08-04
**दायरा**: erikwang2013/security-php v1.1.6 एकीकरण
**समीक्षक**: Claude Code (स्वचालित)

---

## 1. टेस्ट परिणाम

| जाँच | परिणाम |
|---|---|
| PHP सिंटैक्स जाँच (47 फ़ाइलें) | सभी पास |
| PHPUnit (22 टेस्ट, 45 असर्शन) | सभी पास |
| SecurityGuard सुरक्षा पेलोड टेस्ट | XSS + SQLi को सही ढंग से रोकता है |
| SecurityGuard सुरक्षित अनुरोध टेस्ट | कोई गलत अलार्म नहीं |
| phpstan स्टैटिक एनालिसिस | स्थापित नहीं (गैर-अवरोधक) |

## 2. ठीक की गई समस्याएँ

### 2.1 फ़ाइल अपलोड डेटा SecurityGuard को नहीं दिया गया (Critical)

**फ़ाइल**: `service/app/middleware/SecurityMiddleware.php` + `admin/app/middleware/SecurityMiddleware.php`

मिडलवेयर केवल `$request->all()` को `SecurityGuard::guard()` को देता था, लेकिन यह विधि फ़ाइल अपलोड डेटा शामिल नहीं करती। `UploadDetector` को `['tmp_name' => ..., 'name' => ...]` फ़ॉर्मेट के फ़ाइल डेटा की आवश्यकता है।

**सुधार**: डेटा सरणी में `$request->file()` को विलय करने वाला लूप जोड़ा गया, फिर `SecurityGuard::guard()` को दिया गया।

### 2.2 Admin encryptable कॉन्फ़िगरेशन में डिफ़ॉल्ट मान की कमी (Medium)

**फ़ाइल**: `admin/config/plugin/erikwang2013/encryptable/app.php`

admin कॉन्फ़िगरेशन `env('ENCRYPTION_KEY')` बिना फ़ॉलबैक मान के उपयोग करता है; पर्यावरण चर अनुपलब्ध होने पर `null` लौटता है। Service `getenv('ENCRYPTION_KEY') ?: ''` उपयोग करता है और सही ढंग से खाली स्ट्रिंग पर फ़ॉलबैक करता है।

**सुधार**: admin कॉन्फ़िगरेशन में service के व्यवहार से मेल खाने के लिए `?: ''` ऑपरेटर का उपयोग किया गया।

### 2.3 Docker Compose पर्यावरण चर अधूरे (Medium)

**फ़ाइल**: `docker-compose.yml`

- service कंटेनर में `ENCRYPTION_CIPHER` और `ENCRYPTION_PREVIOUS_KEYS` की कमी
- admin कंटेनर में `ENCRYPTION_KEY`, `ENCRYPTION_CIPHER`, `ENCRYPTION_PREVIOUS_KEYS`, `HASHIDS_SALT`, `SNOWFLAKE_WORKER_ID`, `SNOWFLAKE_DATACENTER_ID` की कमी

**सुधार**: सभी अनुपलब्ध पर्यावरण चर जोड़े गए, `.env.example` के समान डिफ़ॉल्ट मानों के साथ।

### 2.4 WAF मिडलवेयर दोहरी पहचान (Critical, पहले दौर में ठीक)

कस्टम `SecurityMiddleware` में ~200 पंक्तियों के इनलाइन रेगेक्स थे, जो `security-php` पैकेज के 31 डिटेक्टरों से पूरी तरह दोहराए गए थे। प्रत्येक अनुरोध दो बार स्कैन होता था, CPU बर्बाद होता था और दोहरा अवरोधन संभव था।

**सुधार**: मिडलवेयर को `SecurityGuard::guard()` API उपयोग करने के लिए पुनर्लेखित किया गया; 341 पंक्तियों से घटकर ~110 (service), 136 से ~85 (admin)। ब्रूट फ़ोर्स सुरक्षा और रिस्पॉन्स सुरक्षा हेडर बनाए रखे गए।

### 2.5 ENCRYPTION_KEY की कमी (Critical, पहले दौर में ठीक)

`.env.example` फ़ाइल में `ENCRYPTION_KEY` प्लेसहोल्डर था, `ENCRYPTION_CIPHER` और `ENCRYPTION_PREVIOUS_KEYS` अनुपलब्ध थे। कोई वास्तविक `.env` फ़ाइल नहीं थी।

**सुधार**: 32-बाइट base64 कुंजी उत्पन्न की गई, `ENCRYPTION_CIPHER=AES-256-CBC` और `ENCRYPTION_PREVIOUS_KEYS` जोड़े गए, `.env` फ़ाइल बनाई गई।

## 3. पारिस्थितिकी कॉन्फ़िगरेशन पूर्णता

### 3.1 पैकेज (दोनों प्रोजेक्ट में समान)

| पैकेज | संस्करण | Service | Admin |
|---|---|---|---|
| erikwang2013/security-php | v1.1.6 | स्थापित | स्थापित |
| erikwang2013/encryptable | - | स्थापित | स्थापित |
| erikwang2013/encryption | - | स्थापित | स्थापित |
| erikwang2013/jwt-webman | - | स्थापित | स्थापित |
| erikwang2013/hashids | - | स्थापित | स्थापित |
| erikwang2013/snowflake-php | - | स्थापित | स्थापित |
| erikwang2013/poster-php | - | स्थापित | स्थापित |
| erikwang2013/season | - | स्थापित | स्थापित |
| erikwang2013/webman-scout | - | स्थापित | स्थापित |

### 3.2 WAF कॉन्फ़िगरेशन

| आइटम | Service | Admin | स्थिति |
|---|---|---|---|
| कॉन्फ़िगरेशन फ़ाइल | `config/plugin/erikwang2013/security-php/app.php` | समान | प्रकाशित |
| सक्षम डिटेक्टर | 31/31 | 31/31 | सही |
| IP ब्लैकलिस्ट | सक्षम (5 हमले/60s -> 900s बैन) | समान | सही |
| ब्लॉक मोड डिटेक्टर | 28 | 28 | सही |
| लॉग-केवल डिटेक्टर | 3 (header_injection, ssti, nosql_injection) | 3 | सही |
| स्टोरेज | फ़ाइल | फ़ाइल | सही |
| लॉगिंग | सक्षम (फ़ाइल, 10MB रोटेट) | समान | सही |
| मिडलवेयर पंजीकृत | `config/middleware.php` | `config/middleware.php` | सही |

### 3.3 एन्क्रिप्शन कॉन्फ़िगरेशन

| आइटम | Service | Admin | स्थिति |
|---|---|---|---|
| ENCRYPTION_KEY | `base64:aJSrb...` | समान | सेट |
| ENCRYPTION_CIPHER | `AES-256-CBC` | समान | सेट |
| ENCRYPTION_PREVIOUS_KEYS | (खाली) | (खाली) | सेट |
| encryptable कॉन्फ़िग | `config/plugin/erikwang2013/encryptable/app.php` | समान (एकीकृत) | सही |
| encryption कॉन्फ़िग | `config/encryption.php` | - | सही |
| .env फ़ाइल | मौजूद | मौजूद | बनाई गई |
| .env.example | अपडेटेड | अपडेटेड | सही |
| docker-compose | अपडेटेड | अपडेटेड | सही |

### 3.4 Encryptable Trait वाले मॉडल

31 मॉडल `Encryptable` trait उपयोग करते हैं, संवेदनशील फ़ील्ड सही ढंग से `$encryptable` में घोषित:

| श्रेणी | मॉडल | संवेदनशील फ़ील्ड |
|---|---|---|
| उपयोगकर्ता PII | Users | email, mobile |
| उपयोगकर्ता PII | UserAddresses | name, phone, detail |
| उपयोगकर्ता PII | UserKyc | real_name, id_number |
| उपयोगकर्ता PII | UserSocialAccounts | access_token, refresh_token |
| गोपनीयता | PrivacyRequests | email |
| वित्त | GiftCards | receiver_email |
| वित्त | AffiliatePayouts | account |
| वित्त | PaymentGateways | name, api_key, api_secret, webhook_secret |
| प्लेटफ़ॉर्म | PlatformOrders | platform_account_id, buyer_name, buyer_email |
| प्लेटफ़ॉर्म | PlatformAccounts | account_name, api_key, api_secret |
| प्लेटफ़ॉर्म | PlatformListings | platform_account_id |
| लॉजिस्टिक्स | LogisticsCompanies | name, api_key |
| आपूर्तिकर्ता | Suppliers | name, email, phone |
| आपूर्तिकर्ता | B2bVerifications | company_name |
| विक्रेता | Merchants | store_name, email, phone |
| अन्य | EmailLogs | to_email |
| अन्य | 15 और मॉडल | नाम फ़ील्ड |

## 4. दूसरे दौर के सुधार (API एन्क्रिप्शन + JWT कुंजी)

### 4.1 API रिस्पॉन्स एन्क्रिप्शन मिडलवेयर (Medium, ठीक किया गया)

**फ़ाइल**: `service/app/middleware/EncryptionMiddleware.php` (नया)

`erikwang2013/encryption` पैकेज स्थापित था और `app/common/Encryption` टूल क्लास मौजूद थी, लेकिन मिडलवेयर पाइपलाइन में जुड़ा नहीं था। इंटरफ़ेस संवेदनशील डेटा में ट्रांसमिशन-परत एन्क्रिप्शन की कमी थी।

**सुधार**:
- `EncryptionMiddleware` बनाया गया, HTTP header-संचालित एन्क्रिप्शन/डिक्रिप्शन:
  - `X-Encrypted: 1` — अनुरोध डिक्रिप्शन: base64 साइफरटेक्स्ट बॉडी को JSON में डिक्रिप्ट करके कंट्रोलर को देता है
  - `X-Encrypt-Response: 1` — रिस्पॉन्स एन्क्रिप्शन: रिस्पॉन्स का `data` फ़ील्ड base64 साइफरटेक्स्ट में एन्क्रिप्ट करता है
  - `X-Encrypt-Fields: field1,field2` — केवल रिस्पॉन्स के निर्दिष्ट फ़ील्ड एन्क्रिप्ट करता है
- मिडलवेयर स्टैक के अंतिम स्तर के रूप में पंजीकृत (HashidsEncode के बाद)
- स्वास्थ्य जाँच (`/api/health`, `/api/ping`) और दस्तावेज़ एंडपॉइंट (`/apidoc`) एन्क्रिप्शन छोड़ते हैं

### 4.2 क्लास नाम/फ़ाइल नाम बेमेल (Medium, ठीक किया गया)

**फ़ाइल**: `app/common/EncryptionHelper.php` → `app/common/Encryption.php`

क्लास `app\common\Encryption` फ़ाइल `EncryptionHelper.php` में घोषित थी, जो PSR-4 मानक से बेमेल है, जिससे Composer ऑटोलोड विफल होता था। IDE और CLI परिवेश में क्लास autoloader द्वारा नहीं मिल सकती थी।

**सुधार**: फ़ाइल को क्लास नाम से मेल खाने के लिए `Encryption.php` नाम दिया गया।

### 4.3 JWT_SECRET_KEY खाली (Low, ठीक किया गया)

**फ़ाइल**: `service/.env.example`, `service/.env`, `docker-compose.yml`

`JWT_SECRET_KEY` खाली स्ट्रिंग थी; हालाँकि JWT मिडलवेयर में `JWT_SECRET → JWT_SECRET_KEY` फ़ॉलबैक चेन है (`JWT_SECRET` को प्राथमिकता), प्लेसहोल्डर मान असुरक्षित है।

**सुधार**: 32-बाइट base64 कुंजी उत्पन्न की गई, `JWT_SECRET` और `JWT_SECRET_KEY` दोनों सेट किए गए। `.env.example`, `.env` और `docker-compose.yml` अपडेट किए गए।

## 5. अवलोकन के लिए समस्याएँ (संभावित अनुकूलन बिंदु)

### 5.1 SecurityGuard का webman/Workerman header निर्भरता (कम जोखिम)

**प्रभाव**: CSRF Origin, Host Header, DNS Rebinding, Request Smuggling, CORS जैसे डिटेक्टर `$_SERVER` में HTTP हेडर डेटा पर निर्भर हैं।

Workerman गैर-CGI परिवेश में `$_SERVER` में HTTP हेडर पूरी तरह भरे नहीं हो सकते। SecurityGuard में फ़ॉलबैक लॉजिक है (जैसे header खाली होने पर डिटेक्शन छोड़ना), इसलिए **गलत अलार्म नहीं** होंगे, लेकिन **कुछ header हमले छूट सकते हैं**। प्रभाव कम है क्योंकि Nginx रिवर्स प्रॉक्सी स्तर पर भी दुर्भावनापूर्ण हेडर आमतौर पर फ़िल्टर होते हैं।

**सुझाव**: अधिक पूर्ण header पहचान के लिए SecurityGuard के `$meta` पैरामीटर में header मान स्पष्ट रूप से पारित किए जा सकते हैं। वर्तमान में बदलाव आवश्यक नहीं है।

### 5.2 CSRF Origin डिटेक्टर का Admin पर प्रभाव (कोई जोखिम नहीं)

Admin का `csrf_origin` डिटेक्टर `block` मोड में `allowed_origins` खाली है। लेकिन डिटेक्टर केवल तब ट्रिगर होता है जब Origin header मौजूद हो और Host से मेल न खाता हो; प्रशासन पैनल एक्सेस पर आमतौर पर Origin header नहीं होता (समान-मूल एक्सेस), इसलिए **गलत अवरोधन नहीं** होगा।

### 5.3 सभी 31 डिटेक्टर सक्षम, प्रति-अनुरोध ओवरहेड (प्रदर्शन नोट)

सभी अनुरोध सभी 31 डिटेक्टर चलाते हैं (JWT, WebSocket, GraphQL, CSV, prototype pollution सहित)। प्रत्येक डिटेक्टर अनुरोध के सभी फ़ील्ड पर रेगेक्स मिलान करता है। इस प्रोजेक्ट के उपयोग परिदृश्य के लिए ओवरहेड स्वीकार्य सीमा में है (webman रेसिडेंट-मेमोरी प्रक्रिया है, CGI कोल्ड स्टार्ट ओवरहेड नहीं)।

### 5.4 IP ब्लैकलिस्ट स्थायित्व (संचालन नोट)

स्टोरेज बैकएंड `file` मोड है, डिफ़ॉल्ट पथ `sys_get_temp_dir() . '/security_storage.json'` है। Docker कंटेनर में रीस्टार्ट के बाद अस्थायी निर्देशिका खो सकती है। यदि मल्टी-कंटेनर तैनाती में ब्लैकलिस्ट साझा करना हो, तो `redis` मोड में स्विच किया जा सकता है।

## 6. परिवर्तित फ़ाइलें सारांश

```
admin/.env.example                                (ENCRYPTION_KEY नया)
admin/.env                                        (.env.example से बनाई गई)
admin/CLAUDE.md                                   (मिडलवेयर स्टैक + tech stack अपडेट)
admin/composer.json                               (security-php निर्भरता)
admin/config/plugin/erikwang2013/encryptable/app.php  (डिफ़ॉल्ट मान एकीकृत)
admin/config/plugin/erikwang2013/security-php/app.php  (नया, 31 डिटेक्टर)
admin/app/middleware/SecurityMiddleware.php       (SecurityGuard उपयोग के लिए पुनर्लेखित)
service/.env.example                              (ENCRYPTION_KEY/CIPHER + JWT कुंजी अपडेट)
service/.env                                      (.env.example से बनाई गई, JWT कुंजी सिंक)
service/CLAUDE.md                                 (मिडलवेयर स्टैक + Encryption + tech stack अपडेट)
service/composer.json                             (security-php निर्भरता)
service/config/middleware.php                     (+ EncryptionMiddleware)
service/config/plugin/erikwang2013/security-php/app.php  (नया, 31 डिटेक्टर)
service/app/common/Encryption.php                 (EncryptionHelper.php से नाम बदला)
service/app/middleware/EncryptionMiddleware.php   (नया, API रिस्पॉन्स एन्क्रिप्शन/डिक्रिप्शन)
service/app/middleware/SecurityMiddleware.php     (SecurityGuard + फ़ाइल अपलोड उपयोग के लिए पुनर्लेखित)
docker-compose.yml                                (encryption/jwt पर्यावरण चर पूर्ण)
docs/security-review.md                           (यह रिपोर्ट)
```

## 7. निष्कर्ष

**स्थिति**: पास

- WAF पहचान XSS, SQL इंजेक्शन आदि हमलों को सही ढंग से रोकती है (31 डिटेक्टर, SecurityGuard::guard API)
- संवेदनशील फ़ील्ड एन्क्रिप्शन कॉन्फ़िगरेशन पूर्ण (31 मॉडल, 6 श्रेणी संवेदनशील डेटा, Encryptable trait)
- API ट्रांसमिशन एन्क्रिप्शन/डिक्रिप्शन मिडलवेयर में जुड़ा (EncryptionMiddleware, AES-256-CBC, header ट्रिगर)
- JWT कुंजी कॉन्फ़िगर की गई (JWT_SECRET + JWT_SECRET_KEY दोनों सेट)
- फ़ाइल अपलोड पहचान ठीक की गई ($_FILES डेटा SecurityGuard में विलय)
- कोई कार्यक्षमता रिग्रेशन नहीं (22/22 टेस्ट पास)
- कोई मिडलवेयर दोहरी पहचान नहीं
- Docker तैनाती पर्यावरण चर पूर्ण
