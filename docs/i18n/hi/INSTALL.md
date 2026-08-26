# क्रॉस-बॉर्डर ई-कॉमर्स प्लेटफ़ॉर्म — स्थापना गाइड

> Cross-border E-Commerce Platform Installation Guide
>
> [चीनी README](../../../README.md) | [English README](../../README-EN.md) | [समीक्षा रिपोर्ट](../../AUDIT-REPORT.md)

---

## आवश्यकताएँ / Requirements

| घटक | न्यूनतम संस्करण | अनुशंसित संस्करण |
|------|----------|----------|
| PHP | 8.3+ | 8.3 |
| MySQL | 5.7+ | 8.0 |
| Redis | 6.0+ | 7.x |
| Composer | 2.x | 2.x |
| Elasticsearch | 7.x | 8.x (वैकल्पिक/optional) |

### PHP एक्सटेंशन

```
curl, json, mbstring, pdo_mysql, redis, fileinfo, bcmath, gd, openssl, zip
```

---

## स्थापना विधियाँ / Installation Methods

### विधि 1 (अनुशंसित): Web एक-क्लिक स्थापना विज़ार्ड

ब्राउज़र से स्थापना पृष्ठ खोलें, डेटाबेस जानकारी और व्यवस्थापक खाता भरें — **टेबल निर्माण, कॉन्फ़िगरेशन और व्यवस्थापक निर्माण पूरी तरह स्वचालित**।

```bash
# 1. निर्भरताएँ स्थापित करें
cd admin/
composer install

# 2. प्रशासन पैनल शुरू करें
php start.php start

# 3. ब्राउज़र से खोलें (पहली बार स्वतः स्थापना पृष्ठ पर पुनर्निर्देशित होगा)
# http://127.0.0.1:8788/app/admin/install/step1
```

स्थापना विज़ार्ड **स्वचालित रूप से** पूरा करता है:
- MySQL डेटाबेस बनाता है (यदि मौजूद नहीं है)
- `install.sql` की सभी 117 टेबलें आयात करता है (7 `wa_` + 110 `erik_`)
- प्रशासन पैनल मेनू आयात करता है
- `plugin/admin/config/database.php` और `thinkorm.php` उत्पन्न करता है
- `service/.env` उत्पन्न करता है (यादृच्छिक JWT/Hashids/एन्क्रिप्शन कुंजियों सहित)
- सुपर व्यवस्थापक खाता बनाता है
- सेवा रीलोड के लिए SIGUSR1 सिग्नल भेजता है

> स्थापना पूर्ण होने के बाद, service/ API सेवा भी शुरू करनी होगी (नीचे चरण 5 देखें)।

---

### विधि 2: मैनुअल स्थापना / Manual Installation

<details>
<summary>कमांड-लाइन तैनाती या मौजूदा डेटाबेस वातावरण के लिए</summary>

### 1. डेटाबेस बनाएँ

```sql
CREATE DATABASE IF NOT EXISTS `shop_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 2. डेटाबेस आयात करें

```bash
mysql -u root -p shop_db < install.sql
```

> `install.sql` में **117 टेबलें** और डिफ़ॉल्ट सीड डेटा शामिल है।

### 3. service/.env कॉन्फ़िगर करें

```bash
cd service/
cp .env.example .env
# वास्तविक डेटाबेस/Redis/JWT आदि पैरामीटर सेट करने के लिए .env संपादित करें
```

**प्रमुख कॉन्फ़िगरेशन आइटम:**

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=erik_shop
DB_USER=root
DB_PASS=your_password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

JWT_SECRET=<यादृच्छिक 32-बाइट कुंजी>
HASHIDS_SALT=<यादृच्छिक नमक मान>
ENCRYPTION_KEY=<यादृच्छिक 32-बाइट कुंजी>
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_DATACENTER_ID=1
```

### 4. admin/ कॉन्फ़िगर करें

```bash
cd admin/
cp .env.example .env
# .env संपादित करें, service के समान डेटाबेस जानकारी भरें
```

### 5. व्यवस्थापक खाता बनाएँ

```sql
-- पासवर्ड bcrypt से उत्पन्न करना होगा
INSERT INTO `wa_admins` (`username`, `nickname`, `password`, `status`)
VALUES ('admin', 'सुपर व्यवस्थापक', '<bcrypt_hash>', 0);

INSERT INTO `wa_admin_roles` (`role_id`, `admin_id`) VALUES (1, 1);
```

</details>

### विधि 3: Docker तैनाती / Docker Deployment

```bash
# 1. पर्यावरण चर कॉन्फ़िगर करें
export DB_PASS=your_db_password
export JWT_SECRET=$(openssl rand -hex 32)
export HASHIDS_SALT=$(openssl rand -hex 8)
export ENCRYPTION_KEY=$(openssl rand -hex 16)

# 2. सभी सेवाएँ शुरू करें
docker-compose up -d

# 3. Web स्थापना विज़ार्ड चलाएँ
# http://localhost/app/admin/install/step1
```

Docker सेवाएँ: Nginx(:80) → service(:8787) + admin(:8788), MySQL(:3306), Redis(:6379), ES(:9200)

---

### सेवाएँ शुरू करें / Start Services

```bash
# निर्भरताएँ स्थापित करें (दोनों प्रोजेक्ट के लिए)
cd service/ && composer install
cd admin/ && composer install

# API सेवा शुरू करें
cd service/
php start.php start -d

# प्रशासन पैनल शुरू करें
cd admin/
php start.php start -d
```

| सेवा | डिफ़ॉल्ट पोर्ट | सत्यापन विधि |
|------|----------|----------|
| API | 8787 | `curl http://127.0.0.1:8787/api/health` |
| प्रशासन पैनल | 8788 | ब्राउज़र से `http://127.0.0.1:8788/app/admin` |

### सीड डेटा आयात करें (वैकल्पिक) / Import Seed Data (Optional)

```bash
cd service/
php start.php seed:countries     # देश/क्षेत्र
php start.php seed:currencies    # मुद्राएँ
php start.php seed:hs_codes      # HS Code कोड
php start.php seed:compliance    # अनुपालन श्रेणियाँ
```

---

## निर्देशिका संरचना / Directory Structure

```
shop-php/
├── install.sql              # विलयित पूर्ण स्थापना SQL
├── admin/                   # प्रशासन पैनल (webman-admin + LayUI)
│   ├── config/database.php  # डेटाबेस कॉन्फ़िगरेशन
│   ├── plugin/admin/        # webman-admin प्लगइन
│   └── start.php
├── service/                 # API सेवा (webman RESTful)
│   ├── config/              # कॉन्फ़िगरेशन फ़ाइलें
│   ├── database/schema.sql  # मूल व्यावसायिक टेबल SQL (install.sql द्वारा प्रतिस्थापित)
│   ├── database/seeders/    # सीड डेटा
│   └── start.php
```

---

## डेटाबेस संरचना अवलोकन / Database Schema Overview

| मॉड्यूल | टेबल उपसर्ग | टेबल संख्या | विवरण |
|------|--------|--------|------|
| प्रशासन पैनल सिस्टम | `wa_` | 7 | व्यवस्थापक/भूमिका/अनुमति/कॉन्फ़िगरेशन/अटैचमेंट |
| उपयोगकर्ता और खाता | `erik_users_*` | 7 | उपयोगकर्ता/पता/सोशल/KYC/पसंदीदा/सदस्यता |
| उत्पाद और श्रेणियाँ | `erik_product_*` | 16 | उत्पाद/SKU/बहुभाषी/बहु-मुद्रा/समीक्षा/अनुपालन/HS |
| कार्ट और ऑर्डर | `erik_order_*` | 9 | कार्ट/ऑर्डर/भुगतान/रिफंड/रिटर्न/कस्टम क्लियरेंस |
| देश/मुद्रा/लॉजिस्टिक्स | `erik_shipping_*` | 11 | देश/मुद्रा/विनिमय दर/लॉजिस्टिक्स/ज़ोन/गोदाम/स्टॉक |
| कस्टम और कर | `erik_hs_*` | 5 | HS कोड/टैरिफ/VAT/अनुपालन प्रतिबंध |
| भुगतान और धन | `erik_payment_*` | 6 | भुगतान गेटवे/प्लेटफ़ॉर्म सेटलमेंट/आपूर्तिकर्ता निपटान/मुद्रा लाभ-हानि |
| मार्केटिंग | `erik_coupon_*` | 9 | कूपन/फ़्लैश सेल/ग्रुप बाय/एफिलिएट |
| आपूर्ति श्रृंखला | `erik_supplier_*` | 7 | आपूर्तिकर्ता/क्रय/गुणवत्ता निरीक्षण |
| जोखिम और अनुपालन | `erik_risk_*` | 6 | जोखिम नियम/GDPR/Cookie/गोपनीयता |
| मल्टी-प्लेटफ़ॉर्म | `erik_platform_*` | 8 | मल्टी-स्टोर/प्लेटफ़ॉर्म खाता/लिस्टिंग/विक्रेता |
| सामग्री और अनुभव | `erik_*` | 12 | CMS/Feed/साइज़ चार्ट/सूचना/ईमेल/खोज/ऑपरेशन लॉग |
| सदस्यता/पॉइंट आदि | `erik_*` | 7 | सदस्यता/पॉइंट/गिफ्ट कार्ड/B2B |
| AB परीक्षण/API/सेटिंग्स | `erik_*` | 7 | AB परीक्षण/रेट लिमिट/API दस्तावेज़/सिस्टम कॉन्फ़िगरेशन |

---

## सामान्य समस्याएँ / Troubleshooting

### MySQL त्रुटि "Specified key was too long"

```sql
-- सुनिश्चित करें कि utf8mb4 + InnoDB उपयोग हो रहा है और innodb_large_prefix सक्षम है
SET GLOBAL innodb_large_prefix = ON;
SET GLOBAL innodb_file_format = Barracuda;
SET GLOBAL innodb_file_per_table = ON;
```

### पोर्ट विवाद / Port Conflict

`admin/.env` या `service/.env` में `APP_PORT` बदलें।

### Redis कनेक्शन विफल

जाँचें कि Redis एक्सटेंशन स्थापित है और Redis सेवा चल रही है:
```bash
redis-cli ping  # PONG लौटाना चाहिए
```

### Snowflake ID विवाद

यदि एक साथ कई सर्वर इंस्टेंस चल रहे हैं, तो सुनिश्चित करें कि प्रत्येक सर्वर का `SNOWFLAKE_WORKER_ID` अलग हो (0-31)।

---

## डेवलपमेंट कमांड संदर्भ / Development Commands

```bash
# service/ (API)
php start.php start          # शुरू करें
php start.php start -d       # डेमॉन प्रक्रिया
php start.php reload         # हॉट रीलोड
php start.php stop           # रोकें
php start.php status         # स्थिति

# admin/ (प्रशासन पैनल)
php start.php start
php start.php start -d
php start.php reload
```
