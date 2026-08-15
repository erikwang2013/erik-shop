# Erik Shop 团队调研明细（7 领域）

> **生成时间**：2026-08 · **生成方式**：多代理团队并行调研（基于实际代码证据，禁止臆测）
> **配套文档**：`docs/PLAN.md`（整合后的项目规划，含评审调整与实施状态）
> **评审记录**：2026-08 评审工程师对照代码复核 18 项论断（16 项正确、2 项因工作区已修复而部分正确）；本明细中 PHPStan 修复建议已按 PHPStan 2.x 实际能力修正（CLI 传参替代 neon 配置）
> **每个领域结构**：现状总结 / 已实现 / 差距 / 风险 / 建议（建议前缀 [高]/[中]/[低] 为优先级）

---

## 1. 服务端业务 API（service/）

### 现状总结
基础架构与安全/支付/搜索/推荐骨架扎实（39 控制器 + 111 模型 + 14 中间件 + 10 定时任务，Stripe/PayPal 真实可用、22 个单测通过），但多项文档宣称"完整"的能力实为占位或未接通：Klarna/Adyen 网关仅配置、下单未计优惠券/运费/关税/风控、读写分离未启用、业务种子数据缺失致全新安装核心接口无数据。

### 已实现
- 支付网关双实现（真实代码）：PaymentGateway.php 中 Stripe（PaymentIntent + webhook 验签 + 退款）与 PayPal（REST v2 OAuth2 + 订单/捕获/退款 + verify-webhook-signature 五字段验签 + 退款 capture id 解析）完整可运行，PaymentController::webhook 用订单状态原子门闩防重复入账并在事务内生成 PlatformSettlements 分账记录
- 支付链路闭环：PaymentController（create/status/methods/webhook）、AdminOpsController::executeRefund 真实执行网关退款并事务落库（退款单 + 支付记录 + 订单状态 + 日志）、PaymentReconcileCron 每 6 小时按网关真实状态对账超 2 小时待支付单
- 中间件栈 14 个按序生效（config/middleware.php）：Cors→Security(security-php SecurityGuard 25+ 类检测器 + Redis 暴力破解计数)→RateLimit(Redis 滑动窗口,6+ 端点规则)→Platform(8 平台识别)→GeoIp(MaxMind)→Locale→HashidsDecode→VersionRoute(API-Version header)→HashidsEncode→Encryption，路由级 PosterVerify/JwtAuth/AdminKey
- 多币种定价与多语言商品真实实现：ProductSkuPrices 分币种独立定价 + ExchangeRates 汇率降级、ProductTranslations 按 locale eager load（ProductController 含 VAT 含税/不含税展示价计算）
- 关税估算真实可用：TariffController 按 ProductHsCodes→TariffRules(dest_country+hs_code)→VatSettings 计算 duty/vat（含免税额阈值与 disclaimer）；ShippingController 按物流分区 + 重量阶梯算运费
- 搜索与推荐真实实现：SearchController 走 webman-scout（Products 模型 Searchable + ES 映射 erik_shop_products）ES 异常降级 MySQL LIKE 并写 SearchLogs；RecommendationCron 近 90 天购买共现计算 Top10 写入 product_recommendations，RecommendationController item-based CF + 热门降级
- 订单核心流程：store 事务内原子扣库存（where stock>=qty decrement，防超卖）、取消恢复库存、KYC/禁售拦截入口、状态机 0-8；CouponController::claim 行锁 lockForUpdate + 原子门闩防超发
- 10 个定时任务全部注册于 config/process.php（汇率/物流轨迹/Feed/推荐/合规/退货超时/价格提醒/支付对账/分账/多平台同步），均带错误日志与未配置时跳过逻辑
- 文档导出真实可用：ExportController PhpSpreadsheet XLSX+CSV（含 HS Code 列）、DocumentController 商业发票/装箱单（dompdf）、HealthController 探活（db/redis 双检）
- 质量工具链齐全：PHPUnit 12.5（22 tests/45 assertions，Security/Jwt/ApiResponse/RedisFacade 4 个文件）、phpstan level 5（phpstan.neon 含 Eloquent 误报豁免）、php-cs-fixer、.github/workflows/ci.yml（PHP 8.3/8.4 + MySQL + Redis 矩阵）
- 基础设施模式真实落地：BaseModel Snowflake 主键、Hashids 编解码中间件自动转换、Jwt.php access/refresh 双 token（JwtAuth 拒绝 refresh 用于业务接口）、encryptable 字段加密、config/risk.php+country.php+geoip.php 运营配置齐备

### 差距
- Klarna/Adyen/Afterpay 仅占位：PaymentGateway::make() 的 match 只支持 stripe/paypal（default 抛异常），PaymentController::methods 显式 filter 仅返回 stripe/paypal（注释自认"避免暴露未实现网关"）；但 docs/api.md 6.1 响应示例含 Klarna 行、features.md 1.0 声称 Klarna BNPL/Adyen，文档与代码不符
- 下单未集成优惠券/运费/关税/风控：OrderController::store 只累加商品小计，不读取文档化的 coupon_id（api.md 5.3），不计算 erik_orders 已存在的 shipping_fee/tax_amount/discount_amount/insurance_fee 字段；config/risk.php 存在但 app/ 内无任何 RiskEngine 调用（features.md 3.3 声称"计算价格(分币种+优惠券)"与"风控打分(RiskEngine::score)"）
- 业务种子数据缺失：install.sql 仅 2 条 INSERT（wa_options/wa_roles 系统表），erik_hs_codes/erik_tariff_rules/erik_payment_gateway_methods/erik_shipping_zones/erik_countries 全部无数据；database/seeders 仅 countries.php 且无任何代码加载它（死文件，CLAUDE.md 声称覆盖国家/HS Code/汇率/物流分区/合规分类/尺码表/风控规则）——全新安装后 countries/payment methods/运费/关税接口返回空
- 读写分离配置未启用：config/database.php 定义 mysql_rw（2 读副本 + sticky）但 app/ 与 config/ 无任何代码引用该连接名，所有模型走默认 mysql；features.md 5.x 声称"DB 读写分离(2 读副本+sticky) 完整"名不副实
- 订阅周期购与多平台商品刊登仅表结构：Subscriptions/SubscriptionOrders/SubscriptionLogs、PlatformListings 模型存在但无控制器/路由/写入代码（多平台仅有依赖外部 URL 的 PlatformOrderSyncCron 拉单）；features.md 声称两者"完整"
- ES 多语言搜索声明过度：CLAUDE.md 声称"ES 索引包含所有语言 title/description 并按 locale 加权"，但 Products::toSearchableArray() 仅索引基础单语言字段；CLAUDE.md 声称的 app/search/ 目录实际不存在（Searchable 内联在模型）
- 客服 WebSocket IM 未实现：仅 ChatSessions/ChatMessages 表结构（features.md 自认"WS 待实现"，一致但确属未完成），且订单状态机"待审核(8)/退款中(6)"无任何写入路径（无审核流程、退款仅 admin executeRefund 直通已退款(7)）
- 测试覆盖窄且与文档表述有出入：仅 4 个单测文件（AUDIT-REPORT.md 自认"无集成测试/无覆盖率报告"），无控制器/订单/支付/中间件集成测试；api.md 13.18 称导出返回 CSV，代码实际 XLSX 默认 + CSV 可选；api.md 2.1 文档化 min_price/max_price 筛选参数在 ProductController::index 未实现

### 风险
- 支付链路缺幂等与事件覆盖：POST /api/payment/create 无幂等键，重复请求生成多条待支付记录；webhook 仅处理 payment_intent.succeeded / PAYMENT.CAPTURE.COMPLETED，refunded/failed 等事件静默忽略，依赖 PaymentReconcileCron（仅查超 2 小时单）兜底
- 分账口径双源漂移：webhook 与 SettlementCron 分别从 config('payment.gateway_fee.*') 与 config('cron.payment_gateway_fee_*') 读费率，两处独立维护；且 webhook 即生成 PlatformSettlements(status=0) 后 SettlementCron 又按 order_id 去重补算，存在重复/口径不一致风险；分账仅平台佣金 + 网关费，supplier_amount 无打款流程、affiliate_amount 恒为 0，多币种分账结算（docs/08-multi-currency-settlement）未闭环
- 全新部署即空数据 + 合规默认放行：业务种子全缺且 config/country.php blocked_countries 默认空数组、kyc_required_countries 仅 KR，OrderController 的禁售/KYC 拦截依赖手工配置，漏配即完全放开
- 搜索依赖脆弱：ES 不可用时整段 try/catch 降级 MySQL LIKE，scout 同步无队列（config/scout.php sync.queue=false），索引与多语言分析器无 CI 覆盖，索引漂移不可控
- 财务精度与状态机缺口：订单金额用 float 累加 round；退款仅整单 status=7 无部分退款；Refunds 状态机 2(驳回)/3(已退款) 仅在 AdminOpsController 单一路径流转，无用户侧退款申请与审核接口（features.md 3.5 退货流程的审核/面单环节依赖 admin 端）

### 建议
- [高] 补齐业务种子数据：将 database/seeders/countries.php 接入 Web 安装向导或首次启动流程，并新增 HS Code 基础库、默认支付方式（stripe/paypal 各 method 行）、VAT/关税规则示例、物流分区种子；否则全新部署核心接口（countries/payment methods/tariff/shipping）返回空
- [高] 打通下单真实计费：OrderController::store 接入优惠券折扣（coupon_id 已文档化）、shipping_fee/tax_amount/discount_amount 落库（字段已存在），与 features.md 3.3/api.md 5.3 对齐，并实现 api.md 2.1 的 min_price/max_price 筛选
- [高] 收敛支付方式声明：二选一——实现 Klarna/Adyen 网关（PaymentGateway::make 扩展，Klarna 配置与 gateway_fee 已就绪）或明确标注"占位"并修正 api.md 6.1 示例，避免前端展示不可用方式；同时为 payment/create 增加幂等键（order_id+gateway 去重）
- [中] 落地风控引擎：实现 RiskEngine::score（参照 config/risk.php 的 checks/velocity），在下单/支付事件旁路打分写 risk_logs + order.risk_score（字段已存在），接通"待审核(8)"状态与人工审核流程
- [中] 启用读写分离或修正文档：为只读查询显式切换 mysql_rw 连接（配置已就绪），或至少在 features.md 标注"仅配置未启用"，消除配置与实现脱节
- [中] 补充集成测试与覆盖率门槛：为订单创建（事务/扣库存/取消）、支付 webhook（验签/幂等/分账）、Tariff/Shipping 计算、Hashids 编解码写 PHPUnit 集成测试（CI 已有 MySQL/Redis 服务可直接复用），并配置 coverage 阈值
- [中] 统一分账费率源并补全分账：webhook 与 SettlementCron 合并为单一费率配置；补充供应商/分销结算写入（MerchantSettlements/SupplierSettlements/AffiliateCommissions 已建表）与打款/提现流程，支撑 docs/08-multi-currency-settlement 多币种结算
- [低] 平台化与客服 WS 扩展：为 PlatformOrderSyncCron 增加 amazon/eBay/Shopee 适配器并实现商品刊登写入 PlatformListings（表已就绪）；客服 IM 实现 WebSocket 服务端与消息收发（ChatSessions/ChatMessages 表已就绪）

---

## 2. 管理后台（admin/）

### 现状总结
管理后台基于 webman-admin + LayUI/Pear Admin 已具备完整的安装向导、RBAC 权限、WAF 中间件栈和 82 个控制器/76 个模型的骨架，但业务层"有控制器无页面"：67 个商城控制器中 59 个是仅绑定模型的纯 CRUD 桩、除跨境面板外无任何 HTML 视图（菜单点击 404），且 ShopOrder/ShopPayment 两个控制器因方法签名不兼容在 PHP 8.3 下类加载即致命错误，订单/支付菜单实际不可用。

### 已实现
- 82 个控制器（15 系统 + 67 shop）与 76 个模型（9 系统 + 67 shop）全部成对存在且带 Copyright 头部，命名空间遵循 plugin\admin\app\controller|model
- 通用 CRUD 基类 Crud 完整实现 select/insert/update/delete 与 tree/select/normal 格式化，含数据权限（dataLimit: personal/auth）、desc 表结构字段白名单过滤 inputFilter、密码 Hash、afterQuery/insertInput/updateInput 等扩展点
- Web 安装向导 InstallController 真实可用：step1 建库 + 校验冲突表 + 导入根目录 install.sql（117 张表）+ 生成 plugin/admin/config/database.php 与 thinkorm.php + 生成 service/.env 与 admin/.env（随机 JWT/Hashids/AES/ADMIN_API_KEY）+ SIGUSR1 重载；step2 创建超级管理员并绑定角色 1；importMenu 将 config/menu.php 递归导入 wa_rules
- 权限体系完整：AccessControl 中间件 + plugin\admin\api\Auth::canAccess（noNeedLogin/noNeedAuth/角色规则匹配/超级管理员 * 通配/401 与 403 分流），依赖 wa_roles/wa_rules/wa_admin_roles 表
- 中间件栈与 features.md 4.2 一致：SecurityMiddleware（erikwang2013/security-php SecurityGuard + 登录暴力破解 5 次/300s + 安全响应头）、PlatformMiddleware（8 平台 UA 识别）、HashidsDecode/HashidsEncode（请求解码与响应 *_id 字段编码）、AccessControl
- 菜单结构 config/menu.php（526 行）：6 个系统组 + 数据分析/商城管理/订单管理/海关税务/物流管理/营销管理/供应链管理 7 个业务组共 27 个商城菜单项，含权重/图标/路由
- 跨境面板 ShopDashboardController + ECharts 视图（Pear Admin 主题，KPI 卡片与 5 张图表容器，引用 echarts@5.5.0 CDN）
- 退款审批 ShopRefundController 为少数含真实业务逻辑的控制器：状态机 0 待审/1 通过/2 驳回/3 已退款，标记已退款前调用 service 内部接口 POST /api/admin/refunds/{id}/execute（X-Admin-Key 鉴权，service 端 AdminOpsController + AdminKeyMiddleware 真实存在），失败拒绝落库
- 订单导出 ShopExportController：PhpSpreadsheet 生成 Excel（订单号/日期/状态/币种/商品金额/优惠/运费/实付金额），barryvdh/laravel-dompdf 生成商业发票 PDF（含明细、币种、海关申报提示）
- 模型统一基于 Snowflake 主键（Base::boot creating 自动生成 string 型 ID），业务模型声明 erik_ 表名并与 service 共享同一 MySQL 连接（plugin.admin.mysql）
- i18n 基础文件存在：admin/resource/translations 下 zh_CN/zh_HK/en/ja/ko 五语言各 48 键
- 质量与部署配套：composer.json 含 phpunit ^12.5 与 php-cs-fixer dev 依赖，admin/Dockerfile + docker-compose（8788 端口）配置完整

### 差距
- **致命缺陷（已实测复现）**：ShopOrderController.php 与 ShopPaymentController.php 重写父类 Crud 方法时签名不兼容（缺 : array / : Response 返回类型），PHP 8.3 类加载即 Fatal error——菜单中「订单列表」「支付记录」一旦访问即崩溃，且会连带 webman 进程报错
- 67 个商城控制器中 59 个为仅含 protected $model 的纯 CRUD 桩，且除 ShopDashboardController 外没有 index() 方法和任何 HTML 视图（view/shop/ 下仅 dashboard/index.html 一个文件）；菜单 href /app/admin/shop/ShopProduct/index 等指向不存在的 action，webman 默认路由精确匹配后落入 fallback 404——整个商城管理 UI（商品/分类/物流/营销等）实际不可用，只有 JSON API
- 跨境面板数据链路双重损坏：视图 fetch /app/admin/shop/shop-dashboard/kpi 与 /chartData（kebab 写法）无对应路由（类名为 ShopDashboardController，已核对 webman App::getController 按文件名精确匹配）；且 ShopDashboardController::kpi/chartData 调用 $this->json(['code'=>0,...]) 传入数组，与 Base::json(int $code,...) 签名冲突必抛 TypeError；区域分布/币种占比/订单状态三图是硬编码示例数据（代码注释标明「示例数据」），CLAUDE.md 声称的「物流时效」图不存在
- 文档声称与代码不符：装箱单 PDF、财务报表 PDF（分币种汇总）在 admin 无任何实现；发货管理 ShopShipmentController 为纯桩（无 HS 申报与轨迹逻辑）；订单导出 Excel 列（ShopExportController.php 第 44-60 行）无 HS Code/关税列，与 CLAUDE.md「含 HS Code/关税/币种」不符；商品「多语言编辑+分币种定价」无对应 UI（ShopProductTranslation/ShopProductSkuPrice 为桩且不在菜单）
- 40 个 shop 控制器不在 menu.php 中（ShopMerchant/ShopPlatformAccount/ShopPlatformListing/ShopPlatformOrder/ShopRiskRule/ShopRiskLog/ShopCms/ShopGiftCard/ShopMembership/ShopPointRule/ShopSubscription/ShopB2b/ShopAbTest/ShopCountry/ShopCurrency/ShopExchangeRate/ShopEmailTemplate/ShopNotification/ShopOperationLog/ShopUserKyc/ShopSetting/ShopOrderDocument/ShopSizeChart/ShopKnowledgeBase/ShopFaq/ShopProductAttr/ShopProductCompliance/ShopProductFeed/ShopPriceAlert/ShopPrivacy/ShopInsurance/ShopInventoryTransfer/ShopApiDoc/ShopShop/ShopMerchantProduct/ShopMerchantSettlement/ShopCountryCompliance/ShopProductHsCode/ShopProductTranslation/ShopProductSkuPrice），无菜单入口只能裸 URL 访问
- 测试覆盖为零：admin/ 无 tests/ 目录、无 phpunit.xml，phpunit ^12.5 仅停留在 composer require-dev（AUDIT-REPORT.md 亦承认「Admin 端自动化测试仍为空」）；php-cs-fixer 在 dev 依赖中但无 .php-cs-fixer 配置，无 CI
- i18n 未接入界面：5 语言翻译文件存在，但插件视图与控制器无任何 trans()/__() 调用（grep 无结果），index.html 顶部无语言切换按钮，与 CLAUDE.md「LayUI 界面文本通过 trans() 函数翻译、语言切换按钮位于顶部导航栏」不符
- ShopPaymentController 本意「支付记录只读」的 insert/update/delete 拦截逻辑因签名错误完全失效；ShopOrderController 本意「不允许直接创建/修改订单」的业务约束同样无法生效

### 风险
- 上线阻断级：ShopOrderController/ShopPaymentController 类加载即 Fatal error（PHP 8.3 实测），订单列表/支付记录两个菜单一打开就报错，且 PHP 致命错误会让 webman 常驻进程整体报错重启
- 「桩控制器」大量存在（59/67）+ 菜单与文档声称完整功能，容易让开发/运维误判功能已上线（菜单在、表在、API 却 404 或空数据），属高误导性技术债
- HashidsEncode 对响应中所有 *_id/id 数字字符串做编码（含 40000 以下 int 不编码的阈值分支），若未来新增业务字段误入 encodeFields 或表内存在非 snowflake 数字 ID，会造成前后端 ID 语义错乱且无测试兜底
- install.sql 与 InstallController 硬编码的 $tables_to_install 冲突表清单（约 117 项）双处维护，新增表时冲突检测极易漏改，install.sql 中如含存储过程/触发器也可能被 splitSqlFile 按分号切分破坏（当前 SQL 未见此类内容，属潜在风险）
- Crud::selectInput 返回 6 个元素而 select() 只解构 5 个（$page 丢弃，分页依赖 Illuminate 全局请求参数）、doSelect 未处理 like 之外字符串操作符等边界，叠加无测试，后续改动回归风险高

### 建议
- [高] 修复签名不兼容：为 ShopOrderController::insertInput/updateInput 补 (Request $request): array、ShopPaymentController::insert/update/delete 补 : Response 返回类型，并新增提交前冒烟脚本（php -l + 反射加载全部 82 控制器）防止复发
- [高] 修复跨境面板数据链路：视图 fetch URL 改为 /app/admin/shop/ShopDashboard/kpi 与 /chartData（或新增 kebab 路由别名），kpi/chartData 改用 $this->success()/Base::json(0,'ok',...) 规范调用，删除/替换硬编码示例图表并补「物流时效」图（如缺失需在文档中如实标注）
- [高] 明确商城管理定位并二选一：为菜单内 27 个控制器补 webman-admin 标准 index.html LayUI 列表页（每个控制器加 index() 渲染视图），或从 menu.php 移除 404 菜单并标注「JSON API only」；优先处理订单/退款/发货等 P0 模块页面
- [中] 建立 admin 测试骨架：新增 phpunit.xml 与 tests/ 目录，优先覆盖 Crud 基类（inputFilter/doSelect/数据权限）、AccessControl 鉴权分支、InstallController（可用临时库 + mock PDO）、ShopRefundController 的远程退款调用（mock service 端点）
- [中] 修正文档过度声明：CLAUDE.md 中装箱单 PDF、财务报表 PDF、发货 HS 申报/轨迹、订单导出 HS/关税列、i18n 语言切换按钮等与代码不符的描述按实际删除或标注 TODO，避免误导规划
- [中] 消除双源表清单：InstallController 的表冲突清单改为解析 install.sql 的 CREATE TABLE 动态生成，或提供校验脚本对比两处一致
- [低] 接入 i18n：在视图/控制器调用 trans() 并在 index.html 顶部加语言切换按钮（文件已就绪仅差接线），或明确 i18n 仅面向 service API 返回值
- [低] 补齐质量工具：新增 .php-cs-fixer.php 配置并接入 CI（对 admin 跑 phpunit + php-cs-fixer --dry-run），承接 AUDIT-REPORT.md 已列出的「Admin 添加测试」后续项

---

## 3. Flutter 客户端（apps/flutter/）

### 现状总结
Flutter 客户端骨架完整（11 页面、11 条路由、5 语言词条表、Dio 三拦截器与后端中间件对齐），但属于"可浏览的演示级"状态：下单/注册/支付三大交易闭环因缺少 address_id 与 PosterVerify 人机验证而在后端被 422/40001 直接拒绝，i18n 仅 1 个页面接入，多币种未贯通 API。

### 已实现
- 技术栈与工程骨架真实存在：pubspec.yaml/lock 锁定 flutter_riverpod ^2.3.0、go_router ^12.0.0、dio ^5.3.0、responsive_framework、cached_network_image、flutter_secure_storage、shared_preferences、intl ^0.20.2；lib/ 共 25 个 Dart 文件，android/ios/macos/linux/windows/web 六平台目录齐全
- GoRouter 配置 11 条路由（app_router.dart）：/、/products、/product/:id、/cart、/checkout、/orders、/profile、/addresses、/login、/register、/order/:id，对应 11 个页面文件均真实存在
- i18n 基础设施：app_localizations.dart 硬编码 5 语言（zh_CN/zh_HK/en/ja/ko）各 32 个翻译键；locale_provider.dart 用 Riverpod StateNotifier + SharedPreferences 持久化语言/币种，localeProvider/currencyProvider 已注册
- Dio 拦截器与后端中间件对齐：_AuthInterceptor（Bearer token + 401 时调 /auth/refresh 重试）、_LocaleInterceptor（Accept-Language + API-Version header，对应后端 LocaleMiddleware/VersionRoute）、_PlatformInterceptor（X-Platform header，对应后端 PlatformMiddleware）
- API 层契约一致：ApiResponse{code,msg,data} 与 PaginatedData{list,total,page,per_page} 匹配后端 ApiResponse::success/paginate 的统一格式；apiBaseUrl 支持 --dart-define 覆盖，Android 模拟器 10.0.2.2 特判
- home_screen 实现 PC/平板自适应：>1024 用 NavigationRail 侧边栏 + 4 列网格，窄屏用 NavigationBar 底部 Tab + 2 列网格（main.dart 定义 MOBILE/TABLET/DESKTOP 三档断点）；product_list 桌面左侧 240px 价格 RangeSlider 侧栏
- 商品模块可用：列表支持 keyword/category_id/sort 参数（后端 ProductController::index 均支持，含 price_asc/desc、sales、newest 排序）、详情页含 SKU ChoiceChip、加购 POST /cart（后端 CartController::store 校验库存并合并同 SKU 数量）；ProductCard 可点击进详情
- 购物车可用：列表字段（id/title/image/price/quantity/selected）与后端 CartController::index 输出一致，支持删除 DELETE /cart/{id}，结算入口跳 /checkout
- 订单模块基础可用：列表（order_no/status_text/pay_amount/currency_code 与 OrderController::index 对齐）、详情（含 items 明细）、取消 POST /orders/{id}/cancel（后端 OrderController::cancel 存在）
- 地址管理可用：/user/addresses 的列表/新增/删除/设默认与后端 UserController 四接口对齐，表单含默认地址标记
- 认证基础可用：login/register 调 /auth/login、/auth/register 并 saveTokens 到 flutter_secure_storage（Token 安全存储），init() 启动时恢复登录态；AuthService 与 ApiClient 共享同一存储 key
- 测试与质量工具：test/widget_test.dart 冒烟测试（1 个 testWidgets，验证 ShopApp 渲染）；analysis_options.yaml 启用 flutter_lints 默认规则集

### 差距
- **下单闭环断裂（致命）**：CheckoutScreen._placeOrder 仅提交 {currency_code}，但 OrderController::store 强校验 address_id（缺省即 422「收货地址不存在」，docs/api.md 5.3 也明确要求 address_id）；且 config/poster.php 将 /api/orders 列入 protected_routes，路由挂了 PosterVerify 中间件，Flutter 不发送 X-Poster-Token → 下单必被 40001「需要人机验证」拒绝
- 支付完全缺失：checkout_screen 仅 GET /payment/methods 展示方式列表，从未调用 POST /payment/create 与 GET /payment/status，下单后无支付发起/结果轮询，与 docs/features.md 2.2 支付时序（C→POST /payment/create→SDK 支付→webhook）不符
- 注册被人机验证阻断：POST /auth/register 受 PosterVerify 保护（poster.php 配置），RegisterScreen 未实现 X-Poster-Token 获取/携带，注册请求必被 40001 拒绝
- i18n 仅建库未落地：AppLocalizations.of 只被 profile_screen.dart 实际调用（全 lib 仅 1 处），其余 11 屏约 66 处硬编码中英文文案（home 'Home'、cart 'Shopping Cart'、register '请填写邮箱和密码'、order_detail '订单已取消' 中英混排），「5 语言界面」承诺无法兑现
- 文档与实际不符：apps/CLAUDE.md 声称「10 条路由」实际 11 条（多 /order/:id）；声称技术栈含 fl_chart、window_manager，但 pubspec.yaml/lock 均无这两个包；features.md「Flutter 5 平台」却列出 6 项平台
- 多币种未贯通 API：客户端 currencyProvider 只用于本地格式化，商品列表/详情/购物车请求均不带 currency 参数（后端默认 USD）；ProductDetailScreen 用硬编码 '$' 且读取 product.display_price（后端只把 display_price 挂在 sku 级，product 级恒为 null）→ VAT 含税行永不显示
- 分页与筛选不完整：ProductListScreen 的 _page 从不递增、无滚动加载（只能看首页 20 条）；OrderListScreen 无分页；桌面价格 RangeSlider 传 min_price/max_price 但后端 ProductController::index 没有任何价格过滤逻辑（仅排序引用 min_price）→ 滑块无效
- 健壮性与登录态缺陷：除 home 外各屏 _load 均无 try/catch，未登录访问 /orders、/user/addresses 等 401 时 DioException 未捕获（加载态卡死/未处理异常）；GoRouter 无任何 redirect 登录守卫（redirect-cnt=0），未登录可直达 /cart /checkout /orders /addresses；Profile「退出登录」是 context.push('/login') 而非 AuthService.logout()，不清 token，属功能 bug
- 死代码与测试缺口：ProductReviewList（product_review_list.dart）已实现但无任何页面引用，商品详情未展示评价；测试仅 1 个冒烟测试，无模型/组件/集成测试；.github/workflows/ci.yml 仅覆盖 PHP（phpunit+语法），无 Flutter analyze/test 任务；assets/images 目录为空但 pubspec.yaml 声明该 asset 目录

### 风险
- 核心交易链路在 Flutter 端不可用：下单（缺 address_id + PosterVerify 40001）、支付（无 /payment/create）、注册（PosterVerify 40001）三处均会被后端拒绝，若按现有代码上线将直接阻断购买转化
- 无登录守卫 + 401 刷新逻辑无并发去重：多请求同时 401 会并发调用 /auth/refresh（api_client.dart 未加锁），且 refresh 失败时无登出兜底，token 状态可能不一致
- i18n 双轨制（词条表 + 66 处硬编码）长期并存会导致界面语言混杂、新增文案直接硬编码，5 语言承诺与 docs/VERSIONS.md「国际化 ✅」声明无法兑现，返工成本持续累积
- 多币种展示与实际支付脱节：界面可切 JPY/KRW 但价格仍按美元硬编码显示、API 仍按 USD 计价，多币种结算金额对不上账，属交易一致性风险
- 无 Flutter CI 门禁且 flutter/dart analyze 在本环境因 SDK 只读无法运行验证：依赖人工 review 25 个文件，编译/静态问题回归风险高（docs/VERSIONS.md 记载的 intl 冲突、pending Timer 等历史问题即缺乏自动化防护）

### 建议
- [高] 打通下单闭环：结算页增加地址选择（复用 /user/addresses，默认地址回填）、提交 address_id+currency_code，并接入后端 PosterVerify 验证流程（获得 X-Poster-Token 后 POST /orders），随后实现 POST /payment/create + GET /payment/status 轮询的支付页
- [高] 全量接入 AppLocalizations：将 11 屏 66 处硬编码文案替换为 translate(key) 并补齐缺失键（地址表单、订单状态、错误提示等），删除 AppTheme.supportedLocales 与 locale_provider.supportedLocales 的重复定义，统一唯一来源
- [高] 增加 GoRouter redirect 登录守卫（未登录访问 /cart /checkout /orders /addresses 重定向 /login），Profile「退出登录」改为调用 AuthService.logout() 后回首页，并清理登录态相关页面状态
- [中] 全屏 _load 增加 try/catch 与错误态/空态 UI（当前仅 home 做了异常降级）；ApiClient 401 刷新加单飞锁与失败登出兜底；购物车补数量加减（PUT /cart/{id}）
- [中] 商品详情/列表请求携带 currency 参数、价格改读 sku.display_price 或 display_price 字段，替换全部硬编码 '$' 为 CurrencyFormatter；后端 ProductController::index 增加 min_price/max_price 过滤，前端实现滚动分页
- [中] RegisterScreen 及敏感操作对接 PosterVerify：实现滑块/拼图验证获得 X-Poster-Token（后端 poster 验证接口或前端集成），确保注册/下单不被 40001 拦截
- [低] 补齐 Flutter 测试：Product/Order 模型 fromJson 单测、路由冒烟（11 条路由可达性）、购物车/地址 widget 测试，并新增 GitHub Actions 的 flutter analyze + flutter test 任务（对齐 PHP 的 ci.yml）
- [低] 修正文档与死代码：apps/CLAUDE.md 路由数 10→11、移除 fl_chart/window_manager 声明；将 ProductReviewList 接入商品详情页或删除；清理空 assets/images 目录或补充占位资源

---

## 4. 鸿蒙客户端（apps/harmonyos/）

### 现状总结
鸿蒙客户端（HarmonyOS NEXT API 12+，ArkTS + ArkUI）已具备可编译的 9 页面 + ApiClient/AppState/ProductCard 完整骨架，后端 API 端点与响应结构全部真实匹配（AUDIT-REPORT 记载 27 个 ArkTS 错误已修复、构建成功），但功能深度停留在"展示层"：结算-下单主链路断裂（缺 address_id）、Profile 为静态壳、无多币种/多语言接入、无测试与 CI、99 个构建产物误入库，整体与 Flutter 客户端差距明显。

### 已实现
- 9 个 ArkTS 页面全部存在并在 main_pages.json 注册（Index/ProductDetail/Cart/OrderList/Checkout/Profile/Login/Register/Search），另有 EntryAbility、ApiClient、AppState、ProductCard，可编译（entry/build 缓存与 AUDIT-REPORT.md M3 修复记录佐证，B+ 评级）
- ApiClient 封装 @ohos.net.http：GET/POST/DELETE、Bearer token、API-Version(2026-05-20)、Accept-Language、X-Platform: harmonyos header，声明式 QueryParams/RequestBody 接口满足 ArkTS 字面量约束
- AppState 单例：token/locale/currency 经 @ohos.data.preferences 持久化（erik_shop 存储），cartCount 拉取 /cart 计算，logout 清理 token
- 后端路由与客户端调用逐条匹配：/auth/login、/auth/register、/products、/products/{id}、/banners、/search、/cart(GET/POST/DELETE)、/orders(GET/POST)、/shipping/calculate、/payment/methods 均在 service/config/route.php 注册且控制器存在
- 响应结构与客户端解析一致：products/orders/search 返回 data.list（含 status_text 中文映射、sort=sales 支持）、cart 返回 items 数组（title/image/price/quantity）、shipping 返回 data.options、payment/methods 仅暴露 stripe/paypal
- 首页实现 Banner 轮播（/banners?position=home）+ 热门商品双列 Grid（/products?per_page=10&sort=sales），含顶部搜索栏与购物车图标入口
- 搜索页实现关键词搜索（/search?keyword=&per_page=40）、结果计数、空态与 loading 态，复用 ProductCard
- 购物车页实现列表/合计计算/删除（DELETE /cart/{id}）与空态展示，可跳转结算
- 商品详情页实现加载态、主图/标题/价格/描述展示、加入购物车（取首个 SKU 调 POST /cart）
- 订单列表实现 Tabs 状态筛选（全部/待付款/已发货/已完成 → status 0/2/4）与 loading/空态
- 登录/注册页调用 /auth/login、/auth/register，注册走 AppState.setToken 持久化
- 结算页展示订单商品/运费选项（Radio 选择）/支付方式并计算合计，支持提交动作
- 平台识别链路完整：X-Platform: harmonyos 与 service/app/middleware/PlatformMiddleware.php 的 8 平台白名单匹配
- 工程配置达标：compatibleSdkVersion 5.0.0(12)（API 12+）、stageMode、deviceTypes phone/tablet/2in1、hvigor modelVersion 5.0.0

### 差距
- **结算-下单主链路断裂**：Checkout.ets placeOrder 仅传 {currency_code:'USD'}，后端 OrderController.php:88-96 必填并校验 address_id（无则 422 收货地址不存在）；CartController.php:113 只结算 selected=1 的商品而 Cart.ets 无勾选能力；客户端无任何地址管理页面（Profile 收货地址菜单 route 为空）——下单必然失败
- 支付流程未接入：Checkout 页虽展示并选择支付方式，但 placeOrder 不传支付参数、不调用 POST /payment/create，与 docs/features.md「支付(Stripe/PayPal)完整」不符
- Profile.ets 为静态壳：isLoggedIn @State 初始 false 且从不读取 AppState（登录后仍显示登录/注册）；「登录/注册」项无 onClick；收藏夹/收货地址/礼品卡/语言/币种/隐私设置 6 个菜单 route 全为空不可用；无退出登录入口
- 登录状态管理双轨不一致：Login.ets 直接 getPreferences 写 access_token/refresh_token 绕过 AppState.setToken，AppState 内存 token 不同步（isLoggedIn() 返回 false）；Register.ets 走 AppState，两处路径分裂；登录成功均不刷新 cartCount
- 首页分类入口永远空白：Index.ets loadData 只请求 /banners 与 /products，categories 数组无任何赋值；Banner 无点击跳转；顶部 Search 组件无 onSubmit 进入搜索页
- 多币种/多语言未接入：AppState.currency 持久化后从未传给 API（Checkout 硬编码 country:'US'/currency:'USD'，shipping 硬编码 dest_country_id:1/weight:500）；全部 UI 文案硬编码中文与 '$'（docs/features.md 293 行亦承认「ArkTS 硬编码」），无 en_US 等资源目录，与 Flutter 5 语言差距明显
- 无测试与质量门禁：apps/harmonyos 下无 ohosTest 目录、无任何 .ets 测试；.github/workflows/ci.yml 仅 PHP 语法检查 + 单测，无鸿蒙构建 job；无 lint/格式工具配置
- 仓库卫生问题：git 跟踪 99 个 entry/build 与 .hvigor 缓存产物（占 131 个跟踪文件的 76%，含 msgpack/tsbuildinfo/编译报告），.gitignore 无鸿蒙忽略规则；且仓库无 hvigorw wrapper 脚本（apps/CLAUDE.md 声称的 `hvigorw assembleHap` 无法直接执行，需全局 hvigor 或 DevEco Studio）
- ApiClient 健壮性不足：request/JSON.parse 无 try-catch、无超时设置；delete() 缺 X-Platform header；refresh_token 存储后从未用于刷新；EntryAbility.onCreate 中 AppState.init() 无 await，首帧页面请求可能先于 token 就绪（竞态）；默认 baseUrl 硬编码 http://10.0.2.2:8787/api 仅适配模拟器

### 风险
- 核心交易闭环未打通：结算下单必返 422（缺 address_id），若按 docs/features.md 的「完整」定位对外发布，主路径直接失败，属于发布阻断级缺陷
- 无测试且 CI 无鸿蒙 job：ArkTS 严格类型（字面量/单根 build 约束）回归风险高，AUDIT-REPORT M3 的 27 个编译错误即前车之鉴，后续任何页面改动无自动化保障
- 构建产物入库 + 无 wrapper：仓库体积膨胀（msgpack 缓存等二进制）、易产生无意义 diff，且新环境无法用文档命令复现构建，CI 接鸿蒙也缺少统一构建入口
- 状态管理双轨（AppState 单例 vs 页面本地 @State + Login 直写 preferences）与无响应式机制：后续接入收藏/地址/币种切换等共享状态时极易出现内存与持久化不一致
- 真机/发布适配缺失：默认 baseUrl 指向 Android 模拟器地址且无平台感知机制（Flutter 已有 M4 修复），HarmonyOS 真机与生产 HTTPS 环境不可用

### 建议
- [高] 打通结算-下单闭环：新增收货地址列表/新建页面对接 UserAddresses 相关 API（后端已具备），Cart 页增加 selected 勾选（后端仅结算 selected=1 商品），Checkout.placeOrder 传 address_id + selectedShipping + 币种，验证 POST /orders 成功
- [高] 修复 Profile 与登录状态一致性：Profile.aboutToAppear 读取 AppState.isLoggedIn() 并响应式刷新，「登录/注册」点击跳转 Login，新增退出登录；统一 Login/Register 均走 AppState.setToken 并调用 refreshCartCount
- [高] 建立测试与 CI 门禁：新增 ohosTest（ArkXTest）至少覆盖 ApiClient 请求解析（可注入 mock server）与 AppState 持久化读写；ci.yml 增加 hvigor 构建 job（使用全局 hvigor 或补充 wrapper），阻止编译回归
- [中] 仓库治理：.gitignore 增加 apps/harmonyos/**/build、**/.hvigor、**/oh_modules 规则并 git rm --cached 清理已入库的 99 个产物；补充 hvigorw wrapper（ohpm 安装 @ohos/hvigor）使 apps/CLAUDE.md 命令可用
- [中] 多币种/多语言接入：Checkout/Index/ProductDetail 改用 AppState.currency 与 QueryParams.currency 传参（后端已支持多币种定价）；UI 文案迁移至 resources/base 及 en_US 语言目录，先补英文以对齐 Flutter 的 i18n 拦截器方案
- [中] 补全首页与搜索体验：loadData 增加 /categories 加载分类入口 Grid、Banner 点击跳转 link_url、顶部搜索框 onSubmit 进入 Search 页；Search 结果接入分页（当前 per_page 40 一次性拉取）
- [中] ApiClient 健壮性增强：统一 try/catch 与超时（http.RequestOptions timeout）、401 时用已存储的 refresh_token 自动刷新重试、delete() 补 X-Platform header、baseUrl 支持运行时配置（仿 Flutter 平台感知）
- [低] 修正初始化竞态与详情页：EntryAbility 中 await AppState.init() 后再 loadContent（或页面等待就绪）；ProductDetail 增加多 SKU 选择 UI 与「立即购买」真实下单动作，商品图片接入 Image 缓存

---

## 5. 安全与合规

### 现状总结
平台在 WAF 攻击检测、JWT 双 token、AES 接口加密、Encryptable 字段加密、Hashids 混淆、支付 Webhook 验签与密钥管理方面有真实且较完整的实现（22 测试全过），但风控规则引擎、KYC、GDPR/CCPA 执行层仅有表结构与管理端 CRUD，核心业务逻辑缺失，与 docs/features.md、docs/VERSIONS.md 声称的"完整/✅"不符。

### 已实现
- WAF 攻击检测：service 与 admin 的 SecurityMiddleware 均封装 erikwang2013/security-php v1.1.6 SecurityGuard，config/plugin/erikwang2013/security-php/app.php 配置 31 个检测器（28 个 block、3 个 log：header_injection/ssti/nosql_injection），含 XSS/SQLi/XXE/SSRF/路径遍历/文件上传/CSRF/Host/DNS rebinding 等，另加 IP 黑名单（5 次/60s→封 900s）、安全响应头（nosniff/DENY/Permissions-Policy/Server 隐藏）
- 暴力破解防护：service 端 Redis 计数器 erik_brute:{ip}:{login|register} 10 次/60s（SecurityMiddleware::checkBrute），admin 端 5 次/300s
- JWT 认证：config/jwt.php（HS256、access 7200s/refresh 1209600s、issuer/audience/leeway），app/common/Jwt.php 空密钥 fail-closed（JWT_SECRET→JWT_SECRET_KEY 回退链），JwtAuth 中间件拒绝非 access 类型 token，AuthController 注册/登录签发双 token、refresh 端点轮换双 token（firebase/php-jwt v6.11.1 锁定）
- AES 接口加密：app/common/Encryption.php（AES-256-CBC、每次随机 IV、base64(iv+密文)、密钥长度校验 16/24/32 字节），EncryptionMiddleware 支持 X-Encrypted:1 请求解密、X-Encrypt-Response:1/X-Encrypt-Fields 响应字段加密，跳过 /api/health、/api/ping、/apidoc，注册为全局中间件栈末级
- Encryptable 字段加密：31 个模型使用 Erik\Encryptable\Encryptable trait（Users 的 email/mobile、UserKyc 的 real_name/id_number、UserAddresses、PrivacyRequests.email、PaymentGateways.api_key 等），密码使用 bcrypt(password+salt) 加每用户随机盐，$hidden 阻止序列化泄露
- Hashids 混淆：config/hashids.php + plugin 配置，HashidsHelper 空盐 fail-closed，HashidsDecode（路由参数 + _id 结尾字段自动解码）与 HashidsEncode 中间件在 service/admin 均启用，控制器对外返回编码 ID
- 支付安全：StripeGateway 用 Stripe\Webhook::constructEvent 验签、PayPalGateway 用官方 /v1/notifications/verify-webhook-signature 验签（需 PAYPAL_WEBHOOK_ID）；PaymentController::webhook 验签→幂等更新（订单 status=0 原子门闩防重复入账）→创建 PlatformSettlements 分账；create 接口只暴露已实现的 stripe/paypal；erik_payments 表含 three_ds_status 字段；admin 退款执行接口经 AdminKeyMiddleware（X-Admin-Key，hash_equals 比较）
- 密钥管理：.env.example/.env 含 JWT_SECRET/JWT_SECRET_KEY/HASHIDS_SALT/ENCRYPTION_KEY/ADMIN_API_KEY/STRIPE_SECRET_KEY 等，.env 在 .gitignore 中；Web 安装向导（InstallController）用 random_bytes 生成随机密钥；Jwt/Encryption/HashidsHelper 对缺失密钥均 fail-closed
- 限流与人机验证：RateLimitMiddleware Redis ZSET 滑动窗口（默认 60s/100 次，登录 60s/10、注册 300s/5、支付 60s/5、下单 10s/3、搜索 1s/10），PosterVerify 保护注册/下单/支付等敏感路由
- 测试与质量工具：tests/ 共 22 tests/45 assertions 实测 ALL PASS（SecurityTest 12 + JwtTest 4 + ApiResponseTest 3 + RedisFacadeTest 3），phpunit.xml、phpstan.neon（level 5）、.php-cs-fixer.php、.github/workflows/ci.yml（PHP 8.3/8.4 矩阵 + MySQL/Redis 服务）；README 记录 composer audit 已知 1 个低危 CVE（firebase/php-jwt <7.0.0，受 jwt-webman ^6.0 约束）

### 差距
- 风控规则引擎未实现：docs/features.md §3.3 声称下单流程含「风控打分(RiskEngine::score)」、VERSIONS.md 声称「风控规则引擎 ✅」，但全项目 grep RiskEngine 0 命中；erik_orders.risk_score/risk_result 字段从未被任何代码写入，RiskRules/RiskLogs 仅为空模型，config/risk.php 只有配置无执行点；service/database/seeders/ 仅 countries.php，无 CLAUDE.md 声称的「风控规则」种子；ShopRiskRuleController/ShopRiskLogController 未挂入 admin 菜单
- KYC 无用户侧提交入口：erik_user_kyc 表/UserKyc 模型/admin CRUD 控制器存在，OrderController 下单时对 kyc_required 国家（config/country.php 仅 KR）校验 status=1，但 service/config/route.php 无任何 KYC 提交/查询路由，用户无法自助提交实名资料，闭环缺失
- GDPR/CCPA 仅有请求登记无执行逻辑：PrivacyController 只写入 privacy_requests（status=pending）并承诺 30 天内处理，无数据删除/导出/opt-out 的实际执行代码；ShopPrivacyController 仅状态 CRUD；config/privacy.php 的 data_retention/retain_on_deletion 无对应清理定时任务；erik_cookie_consents 表无任何写入端（无 Cookie Consent 前端组件或 API）
- 客户端未消费 AES 接口加密且鸿蒙端 token 明文存储：Flutter/HarmonyOS 请求仅携带 Authorization/Accept-Language/API-Version/X-Platform，grep 无 X-Encrypted/X-Encrypt-Response 支持（接口加密仅服务端单向能力，docs 所称「三层加密」端上未生效）；鸿蒙 AppState 用 @ohos.data.preferences 存 token（明文），Flutter 用 flutter_secure_storage，跨端安全不一致
- 3DS 无显式代码证据：StripeGateway::createPayment 未设置 payment_method_options[card][request_three_d_secure]，three_ds_status 字段从未写入，3DS 依赖 Stripe 默认策略；README/features.md 声称的「3DS 验证」无代码支撑；Klarna/Adyen 仅 config 占位（PaymentController 注释说明已在前端过滤），与 README 声称「Stripe/PayPal/Klarna/Adyen、BNPL」不一致
- 认证缺失项：无忘记密码/密码重置流程（grep forgot/reset 无命中）、无邮箱验证、JWT 无吊销机制（改密/登出后 token 仍有效）、refresh 端点无单独限流
- CI 未纳入依赖安全审计与静态分析：composer audit 仅出现在 README 文档，ci.yml 只有 PHP 语法检查 + PHPUnit，无 composer audit/phpstan/php-cs-fixer 步骤；phpstan.neon excludePaths 排除了 config/plugin（含安全插件配置）

### 风险
- 依赖安全阻塞：firebase/php-jwt v6.11.1 处于文档声明的 CVE-2025-45769（<7.0.0）影响范围，受 erikwang2013/jwt-webman ^6.0 硬约束无法升级，属于长期未关闭的已知漏洞敞口（HS256 对称用法不受影响，但需持续跟踪上游）
- 检测覆盖盲区：csrf_origin/host_header/dns_rebinding/request_smuggling 等 header 类检测器依赖 $_SERVER，在 Workerman 非 CGI 环境下可能漏检（docs/security-review.md §5.1 自述）；IP 黑名单 file 存储于 sys_get_temp_dir，Docker 重启丢失、多实例不共享，攻击者轮换 IP 即可绕过（配置已预留 redis 存储但未启用）
- 合规声明与实现不符的敞口：按文档对外宣称「风控完整/KYC 完整/GDPR 完整」，实际订单创建无任何风控打分即放行（欺诈订单风险）、KYC 无法自助提交、删除请求无人执行，若以此对外承诺合规能力将构成实质合规风险
- 密钥管理偏弱：安装向导用 bin2hex(random_bytes(16)) 生成 ENCRYPTION_KEY（32 个 hex 字符=128bit 熵，未达 256bit）、bin2hex(random_bytes(8)) 生成 HASHIDS_SALT（64bit 熵）；ENCRYPTION_PREVIOUS_KEYS 无轮换自动化；webman 常驻进程改 .env 需 reload 才生效

### 建议
- [高] 实现风控规则引擎：按 config/risk.php + erik_risk_rules 表落地 RiskEngine::score（事件 user_register/user_login/order_create/payment_create/refund_request），在 OrderController::store/PaymentController::create/AuthController 调用，写入 risk_score/risk_result 与 RiskLogs，旁路模式下高分订单置 status=8 待审核（对应订单状态机「待审核」分支），并将 ShopRiskRule/ShopRiskLog 挂入 admin 菜单
- [高] 补齐 KYC 闭环：新增 POST /api/kyc（实名资料提交，real_name/id_number 走 Encryptable 加密）、GET /api/kyc/status，admin 审核通过置 status=1，与 OrderController 现有校验衔接；补齐 KYC 种子/示例数据
- [高] 实现 GDPR/CCPA 执行层：新增隐私请求处理定时任务（按 retain_on_deletion 保留税务字段、deleted_user_grace 30 天宽限期后删除用户数据、生成 data_portability 导出文件、opt_out 写屏蔽标记）；新增 Cookie Consent 组件与 POST /api/privacy/cookie-consent 写入 erik_cookie_consents；data_retention 配置落地为清理任务
- [高] 客户端接入接口加密与安全存储：Flutter/HarmonyOS 支持 X-Encrypted/X-Encrypt-Response（密钥经安全通道协商下发），鸿蒙端 token 改用 KeyStore/security.asset 存储替代 preferences 明文
- [中] 支付安全加固：Stripe createPayment 显式设置 request_three_d_secure='automatic' 并回写 three_ds_status；payments 查询/退款接口补充用户归属校验（status 已校验，退款/导出需复查）；同步修正 README/VERSIONS 中 Klarna/Adyen「完整」表述或补齐实现
- [中] 依赖安全纳入 CI：ci.yml 增加 composer audit、phpstan（放开 config/plugin 或单独校验）与 php-cs-fixer --dry-run 步骤；建立 CVE 跟踪，jwt-webman 支持 php-jwt ^7 时立即升级
- [中] 认证加固：实现密码重置流程（邮箱验证码 + 一次性重置 token）；JWT 吊销（Redis 黑名单或 token 版本号，改密/登出后失效）；refresh 端点接入限流与重放检测
- [低] 密钥与检测加固：ENCRYPTION_KEY 改为 raw 32 字节 base64 密钥（256bit 熵）、hashids salt 加强至 ≥16 字节；security-php storage 切 redis 模式共享 IP 黑名单；按 security-review.md §5.1 建议向 SecurityGuard 的 $meta 显式传 header，补全 header 类检测

---

## 6. 部署 / 数据 / 测试质量

### 现状总结
Erik Shop 的部署编排（nginx→service:8787/admin:8788 + MySQL/Redis/ES）、117 张表结构与单元测试（22 tests/45 assertions 实测全部通过）基础扎实且文档-代码基本一致，但静态分析工具开箱即崩（PHPStan 128M 内存限制）、admin 侧质量配置完全缺失、GeoIP 数据文件缺失、部分定时任务因外部 API 未配置实际空转，生产容器存在 dev 依赖与无认证中间件暴露风险。

### 已实现
- docker-compose.yml 完整编排 6 个服务（nginx/service/admin/mysql/redis/elasticsearch），均带 healthcheck + depends_on 条件启动 + 命名数据卷 + 桥接网络，`docker compose config` 实测校验通过；nginx 经 docker/nginx/conf.d/shop.conf 以 keepalive upstream 反代 service:8787 与 admin:8788（api.erik.xyz/admin.erik.xyz 两虚拟主机）
- service/Dockerfile 与 admin/Dockerfile 均基于 php:8.3-cli-alpine，安装 pdo_mysql/bcmath/opcache/gd/intl/sockets/redis 等扩展并 composer install --no-dev --optimize-autoloader（service 侧还含 OPCache 生产配置 docker/opcache.ini）
- CI（.github/workflows/ci.yml）配置 PHP 8.3/8.4 矩阵 + MySQL 8.0/Redis 7 服务容器，执行 composer install、php -l 语法检查（service+admin 目录）与 PHPUnit；Makefile 提供 start/stop/test/lint/check/fix/install/docker-up 等 14 个命令
- PHPUnit 实测运行通过：22 tests / 45 assertions ALL PASS（SecurityTest 12 + JwtTest 4 + ApiResponseTest 3 + RedisFacadeTest 3，phpunit.xml 使用 12.5 schema）
- phpstan.neon 配置 level 5（paths=app+config，含 Eloquent/webman 动态方法 ignoreErrors）；实测在 --memory-limit=1G 下 0 错误
- service/.php-cs-fixer.php 配置 PSR-12 + no_unused_imports/ordered_imports 等规则，覆盖 app+config；.editorconfig 统一编码缩进
- install.sql 实测含 117 张表（7 张 wa_ 系统表 + 110 张 erik_ 业务表，InnoDB/utf8mb4_unicode_ci），且 service 侧 110 个业务模型与 110 张 erik_ 表一一对应（B2B/订阅/供应链等 12 个模块表齐备），由 MySQL 官方镜像 docker-entrypoint-initdb.d 自动导入
- Web 安装向导真实存在（admin/plugin/admin/app/controller/InstallController.php step1/step2），导入根目录 install.sql 并生成 service/.env 与 admin/.env（含随机 JWT/Hashids/AES 密钥）；密钥类 .env 文件已被 .gitignore 排除、不入库
- 10 个定时任务进程（config/process.php 注册 exchange_rate/shipment_tracking/product_feed/recommendation/compliance/return_expire/price_alert/payment_reconcile/settlement/platform_order_sync cron）各有独立常驻进程与周期
- 中间件栈按文档注册：Cors→Security→RateLimit→Platform→GeoIp→Locale→HashidsDecode→VersionRoute→HashidsEncode→Encryption（config/middleware.php 实测 10 个全局 + PosterVerify/JwtAuth/AdminKey 路由级，14 个中间件与文档一致）
- docs/api.md 的 71 个 API 端点与 service/config/route.php 实测基本一一对应（含 /health 健康检查真实检测 db+redis）；docs/features.md 中 Flutter 25 文件、HarmonyOS 14 文件、Admin 82 控制器/76 模型均与代码统计一致

### 差距
- PHPStan 开箱不可用：phpstan.neon 未配置 memoryLimit，默认 128M 下并行 worker 直接崩溃（实测复现 'reached configured PHP memory limit: 128M'），Makefile check 目标与 CI 均未带 --memory-limit，静态分析门禁实际跑不通
- admin 侧质量配置全缺：无 phpstan.neon、无 .php-cs-fixer.php、无 phpunit.xml、无 tests/ 目录，且 composer.json 的 require-dev 无 phpstan；实测 `make fix` 的 admin 段（admin && vendor/bin/php-cs-fixer fix）在无配置时进入交互式 'create config file?' 提示而卡死，`make check` 也只覆盖 service
- CI 未集成 phpstan 与 php-cs-fixer（只有 php -l + PHPUnit），且仅测 service；CI 已起 MySQL/Redis 服务容器但没有任何集成测试连接 MySQL，测试覆盖率仍停留在 4 个工具类，111 模型/39 控制器/14 中间件/10 定时任务零测试
- GeoIP 数据文件缺失：config/geoip.php 指向 service/database/geoip/GeoLite2-Country.mmdb，但该目录实测为空、无任何下载/引导脚本，GeoIpMiddleware 只能走 file_exists 降级分支，features.md 声称'GeoIP 完整'名不副实
- 3 个定时任务因外部 API 未配置而空转：config/cron.php 中 tracking_api_url、compliance_source_url、platform_sync_url 均为空字符串，ShipmentTrackingCron/ComplianceCron/PlatformOrderSyncCron 只能记录'跳过'日志（代码注释亦确认）；客服 WebSocket 实时 IM 未实现（features.md 自述'WS 待实现'，无 chat 控制器/WS 进程）
- 生产部署镜像被源码卷覆盖：docker-compose.yml 将 ./service:/app、./admin:/app 挂载进容器，覆盖 Dockerfile 中 COPY + composer install --no-dev 的产物，且 service/、admin/ 均无 .dockerignore，生产容器实际运行宿主机 vendor（含 dev 依赖）
- 文档不一致与闲置配置：docs/deployment.md 两处写 admin 监听 8787 / 'admin.erik.xyz → admin:8787'（实际 8788）；nginx 挂载 ./service/public:/var/www/static:ro 但任何 server 块都未使用该静态目录
- Elasticsearch 与 Redis 安全薄弱：compose 中 ES 设 xpack.security.enabled=false 且 9200 端口暴露宿主机、无任何认证；Redis requirepass 依赖 ${REDIS_PASS:-} 默认空密码且 6379 暴露，.env 未配置时中间件裸奔

### 风险
- 生产环境密钥/鉴权缺失连锁风险：compose 默认占位符（change_me 系）未替换即可启动、ES 无认证、Redis 默认无密码、服务端口全暴露，一旦 .env 配置不完整直接上线，攻击面覆盖 9200/6379/3306/80
- 测试质量形同虚设的风险：22 个单元测试仅覆盖工具类，无任何模型/控制器/中间件/数据库集成测试，且 CI 无静态分析门禁（PHPStan 崩溃、php-cs-fixer 未进 CI），重构与合并不设防，回归问题只能靠人工
- 生产容器运行 dev 依赖：源码卷挂载覆盖镜像 + 无 .dockerignore，--no-dev 优化被绕过后容器内 vendor 含 PHPUnit/phpstan 等 dev 包，既膨胀镜像又违背'生产无 dev 依赖'约定
- 外部依赖空转导致数据可信度风险：物流轨迹/合规规则/平台订单同步三个 cron 默认不执行任何真实同步，若运营方误以为'已自动化'将出现轨迹不更新、合规规则过期、多平台订单漏同步的静默故障
- GeoIP 降级导致区域定价/语言识别失效：mmdb 缺失时所有请求回落到 config('geoip.default')（固定 US/USD/en），跨区用户将按美国默认展示价格与语言，直接影响多币种/多语言核心卖点的准确性

### 建议
- [高] 修复 PHPStan 门禁：**不要**在 phpstan.neon 配 memoryLimit（PHPStan 2.2.8 已移除该 neon 参数，配置会报 `Unexpected item`），改为 `make check` 与 CI 的 phpstan 命令带 `vendor/bin/phpstan analyse --no-progress --memory-limit=1G`，实测可 0 错误通过（已落地，见 docs/PLAN.md 实施状态）
- [高] 补齐 admin 质量配置：新增 admin/phpstan.neon（level 5、paths=app+plugin/admin/app）与 admin/.php-cs-fixer.php（复用 service 规则），并将 admin 纳入 CI 的 phpstan/php-cs-fixer --dry-run 检查；在 admin 质量配置落地前先从 Makefile fix 目标临时移除 admin 段以避免交互卡死
- [高] 修复生产镜像构建：docker-compose.yml 移除 ./service:/app 与 ./admin:/app 源码挂载（或改为仅挂载 runtime/logs 目录），新增 service/.dockerignore 与 admin/.dockerignore（排除 vendor/runtime/.git 等），确保容器内仅运行 --no-dev vendor
- [高] 补集成测试并接入 CI：利用 CI 已起的 MySQL/Redis 服务容器，新增 service/tests 下数据库冒烟/路由级集成测试（如 install.sql 可导入性、健康检查、注册-登录闭环），把 '22 tests' 从纯单元测试扩展为可防回归的门禁
- [中] 解决 GeoIP 数据文件：提供脚本/文档引导下载 GeoLite2-Country.mmdb 到 service/database/geoip/（或启用 config 中 MAXMIND_LICENSE_KEY 自动更新），并在 README/INSTALL 注明缺失时降级 US 默认值的影响
- [中] 收紧中间件安全暴露面：docker-compose.yml 将 ES/Redis/MySQL 端口绑定改为 127.0.0.1（仅 nginx 暴露 80/443），为 ES 开启 xpack 认证或在 compose 注释中明确生产必须配置 REDIS_PASS/ES 安全组，避免裸奔上线
- [中] 消除外部依赖空转：在 config/cron.php 的 3 个空 URL 处补充醒目注释并让日志提升为 WARNING 级（或提供管理后台配置入口），同时在 features.md 中将'物流轨迹/合规更新/多平台同步'状态从'完整'改为'依赖外部 API 配置'，与代码事实对齐
- [低] 清理文档与闲置配置：修正 docs/deployment.md 中 admin 端口 8787→8788 两处笔误；删除 nginx 挂载中未使用的 ./service/public:/var/www/static:ro 卷或补充静态文件 server 块；在 features.md/README 中明确'客服 WebSocket IM 未实现（仅表结构）'避免销售口径误导

---

## 7. 文档与功能覆盖

### 现状总结
文档体系齐全（8 张架构图 SVG+MMD、api.md/architecture/design/deployment/VERSIONS/AUDIT 等 9 份文档）且多数数字口径与代码吻合（73 个路由端点、117 张表、22 tests/45 assertions 实测通过、service/admin 各 5 语言 × 45 条翻译、19 种币种种子），但 features.md/VERSIONS.md/README 对多平台刊登、风控规则引擎、Klarna/Adyen 支付、四线分账、商业发票 PDF、订阅周期购/AB 测试、WebSocket 客服等标注"完整/✅"的功能，实际仅为表结构 + admin CRUD 或完全无业务实现，属系统性"文档超前于代码"。

### 已实现
- 8 张架构图齐全（01-08 SVG 均为真实渲染产物 15-153KB，含对应 .mmd 源），与 docs/diagrams.md 图例索引一一对应
- service 路由实际 73 个（23 公开 + 47 认证 + 1 Webhook + 1 Admin + 1 /health），与 docs/api.md 71 端点、architecture-full.md 73 端点基本一致；23 个公开端点全部存在于 route.php
- service 39 控制器/111 模型/14 中间件、admin 76 模型/5 中间件、10 个 Cron 进程（process.php）、install.sql 117 张表（110 erik_ + 7 wa_）与 README 数字全部吻合
- 测试真实可跑：phpunit 实测 22 tests/45 assertions ALL PASS（SecurityTest 12 + JwtTest 4 + ApiResponseTest 3 + RedisFacadeTest 3）；phpstan level5、php-cs-fixer、Makefile 14 命令、CI（PHP 8.3/8.4 矩阵 + MySQL + Redis）全部存在
- i18n 落地：service/admin 各 5 语言 × 45 条翻译文件、Flutter 手写 AppLocalizations 5 语言 + SharedPreferences 持久化、LocaleMiddleware 5 语言匹配、Accept-Language/API-Version/X-Platform header 规范齐全
- 支付：Stripe（PaymentIntent + client_secret + 3DS）与 PayPal（REST v2 OAuth2 + Webhook 五字段验签）完整网关实现；Webhook 验签→更新支付/订单→生成 PlatformSettlements 事务闭环（含原子门闩防重复入账）
- 社交登录 Google/Apple/Facebook id_token fail-closed 验证（tokeninfo/JWKS/debug_token）+ 绑定/防邮箱接管逻辑；ExportController XLSX+CSV 含 HS Code 列；B2B 询价/拼团/秒杀/优惠券锁等业务真实存在
- 安全：security-php 31 个检测器配置（service/admin 一致）、RateLimitMiddleware 7 条规则（默认 + 6 端点）、DB 读写分离 2 读副本 + sticky=true、45 个模型 SoftDeletes、PosterVerify（slide/click/rotate）Redis 验证
- 多语言商品/多币种定价（19 种币种子）、ES 搜索（scout + MySQL LIKE 降级）、ProductFeedCron 生成 Google/Meta TSV、ExchangeRateCron 每小时拉汇率、ECharts 仪表盘（6 KPI + 3 图表）、Web 安装向导（建库→导入 install.sql→生成 .env→创建管理员）
- 客户端：Flutter 25 Dart 文件/11 页面（Riverpod + GoRouter + responsive_framework PC/平板自适应，仅 2 处硬编码中文）；HarmonyOS 9 页面 ArkTS（含 ApiClient/ProductCard/AppState）

### 差距
- Klarna/Adyen 支付未实现：PaymentGateway::make() 仅支持 stripe/paypal（service/app/common/PaymentGateway.php），PaymentController.php:34 明确注释'仅返回已实现网关，避免暴露 Klarna/Adyen 等未实现配置'，但 README.md 与 VERSIONS.md 将其列为 ✅，仅 features.md 承认'Stripe 完整,其他占位'
- 商业发票/装箱单 PDF 未实现：composer.json 含 barryvdh/laravel-dompdf 但全项目（service/admin）零 Dompdf 调用；DocumentController.php 只读取已有 erik_order_documents 记录，无任何生成逻辑（order_documents 记录也不会被自动创建），features.md 却声称'商业发票 PDF/装箱单'完整
- 风控规则引擎未实现：app/common 8 个类中无 RiskEngine，OrderController::store 无风控打分，订单状态 8(待审核) 永不被写入；features.md 声称'规则引擎(旁路打分:地址校验/邮编匹配/3DS/批量注册/货值异常) 完整'且订单状态机含'待付款→待审核:风控高分'分支，实际不可达
- 四线分账仅实现一线：webhook 与 SettlementCron 只创建 PlatformSettlements；MerchantSettlements/SupplierSettlements/AffiliatePayouts 全项目无任何 ::create 写入（仅表 + admin CRUD），README 与 08-multi-currency-settlement 图声称'四线独立结算'
- 订阅周期购与 AB 测试无服务端 API（仅表 + admin CRUD 控制器，route.php 无对应路由）；多平台'Amazon/eBay/Shopee/Lazada/Temu 商品刊登 + 订单聚合'无真实平台集成，仅 PlatformOrderSyncCron 按通用 URL 拉取（PlatformListings 无业务写入）；WebSocket IM 未实现但 VERSIONS.md 标 ✅（features.md/README 已诚实标注）
- 库存流水不可变账本未落地：InventoryLogs 无任何业务写入（下单扣库存不记流水）；CurrencyExchangeGainsLosses 汇兑损益表也无写入逻辑，README 声称的'库存流水(不可变账本)'与'汇兑损益追踪'仅停留在表结构层
- 种子数据不随安装导入：install.sql 仅含表结构与 wa_ 系统种子（wa_options/wa_roles），countries/currencies/payment_gateway_methods/hs_codes/shipping_zones 等基础数据需手动执行 service/database/seeders/countries.php（InstallController 只导入 install.sql），全新安装后商品/支付方式/运费计算开箱为空；AUDIT-REPORT 却标'数据库种子数据 OK'
- hg/apidoc 动态文档名不副实：仅 AuthController + ProductController 有 @Apidoc 注解（59 行），其余 36 个控制器零注解，6 分组自动文档覆盖严重不足；且存在数字口径偏差（admin 控制器实际 80 vs 文档 82、HarmonyOS 源码 13 个 vs 文档 14、翻译 45 条 vs AUDIT 称 48 条、features.md 中间件管道图漏 RateLimit/Encryption）

### 风险
- 文档系统性夸大'完整'标注（多平台、风控引擎、订阅/AB、四线分账、发票 PDF、Klarna/Adyen、WS 客服），对商业授权客户形成功能交付预期落差，存在合同与信任风险
- 全新安装后基础数据为空（种子不自动导入、向导不跑 seeder），countries/currencies/payment_gateway_methods 等核心数据表无数据，商品列表、支付方式、运费/关税计算等主链路开箱不可用
- 动态 API 文档覆盖仅 2/38 控制器，Flutter/HarmonyOS 客户端对接缺乏权威接口依据；docs/api.md 静态文档与 route.php 存在端点漂移风险（71 vs 73，且 features.md 内部管道图不一致）
- 测试覆盖仅 22 个单元测试（安全 + JWT + 响应 + Redis），38 个业务控制器零测试、admin 无测试、无集成测试与覆盖率报告，批量重构/升级回归风险高
- DB 中 payment_gateway_methods 仍含 klarna/adyen 等未实现网关行，配置被误启时前端可展示但下单后无网关处理，支付链路存在隐性失败点

### 建议
- [高] 全文档统一'已实现/表结构已建/规划中'三态标注：修正 features.md/VERSIONS.md/README 中 Klarna/Adyen、多平台刊登、风控引擎、订阅/AB、四线分账、发票 PDF、WS 客服的状态，杜绝文档超前于代码
- [高] 安装向导（admin/plugin/admin/app/controller/InstallController.php）增加基础种子数据自动导入（countries/currencies/payment_gateway_methods/hs_codes/shipping_zones），保证全新安装开箱可用
- [高] 补齐核心业务闭环：实现 RiskEngine 打分与订单状态 8、用已引入的 dompdf 实现 invoice/packing-list PDF 生成（DocumentController 改为按需生成 + 落库）、库存扣减写 InventoryLogs、webhook 后补 Merchant/Affiliate 分账
- [中] 为全部 73 个路由补齐 @Apidoc 注解以恢复 hg/apidoc 6 分组文档真实覆盖；若短期无法完成，先下修 README 中 apidoc 声明并明确 docs/api.md 为权威静态文档
- [中] 增加集成测试：利用 CI 已配置的 MySQL/Redis 服务补注册→登录→商品→购物车→下单→支付 mock 冒烟链，并补 admin 核心 CRUD 测试，提升 38 个零覆盖控制器的回归防护
- [中] 修正数字口径：admin 控制器 82→80、HarmonyOS 文件数、翻译 key 数 48→45，并统一 features.md 中间件管道图（补 RateLimit/Encryption）与 api.md 端点清单（对齐 73 路由）
- [低] 为 CurrencyExchangeGainsLosses（结算时汇率对比）与 PlatformListings（平台刊登写入）补真实业务逻辑，或改标'表结构已建'；未实现前不再宣称'完整'
- [低] 建立 route.php↔docs/api.md 端点一致性检查脚本并纳入 CI，自动拦截文档与代码的进一步漂移
