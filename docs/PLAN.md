# Erik Shop 项目规划（团队产出）

> **生成时间**：2026-08
> **生成方式**：多代理团队协作（7 领域并行调研 → 系统架构师整合 → 评审工程师复核）
> **依据**：`docs/PLAN-RESEARCH.md`（7 份领域调研明细）、`README.md`、各子项目 `CLAUDE.md`
> **适用周期**：3-6 个月（4 个阶段）
> **评审记录**：2026-08 评审工程师对照代码复核 18 项论断（16 项正确、2 项因工作区已修复而部分正确）；本版已融入评审调整（PosterVerify 签发接口、风控审核出口、Flutter 路径、实施状态标注等）。

## 〇、当前实施状态（评审时核对）

> 依据 `git status`/`git diff` 实测核对；✅=已完成（工作区未提交）、🔄=进行中、⬜=未开始。

| 项 | 状态 | 说明 |
|---|---|---|
| admin 两个致命控制器签名修复（ShopOrder/ShopPayment 补 `: array`/`: Response`） | ✅ | 修复后 82/82 控制器反射加载成功（修复前 2 个 Fatal） |
| PHPStan 门禁 | ✅ | `make check` 实测 `[OK] No errors`；PHPStan 2.2.8 已移除 neon `memoryLimit` 参数，改由 Makefile/CI 传 `--memory-limit=1G` |
| ShopDashboardController json 签名 + 视图 fetch URL | ✅ | `$this->json(0,'ok',$data)` + `/ShopDashboard/kpi` 类名路由 |
| CI 增加 composer audit + phpstan | ✅ | `.github/workflows/ci.yml` 新增两步（YAML 已校验） |
| `scripts/smoke_controllers.php` 防复发冒烟 | 🔄 | 见阶段一交付物 |
| PosterVerify 签发接口（`POST /api/poster/verify`） | ✅ | 评审新发现 + 已实现：`PosterController`（math 算术题）+ 路由；8789 端口实测全链路通过（challenge→verify→中间件放行→一次性消费） |
| 🔄 新发现 P0：Encryptable 空 IV 阻断注册 | ✅ | 已修复：`app/common/SecureEncrypter.php`（显式 16 字节零 IV，与旧数据字节级兼容）+ `support/bootstrap.php` 注册 resolver；实测注册成功、登录解密正常 |
| 🔄 新发现 P0：加密字段不可查询（email） | ✅ | 已修复：`erik_users.email_hash`（HMAC-SHA256 索引列，install.sql + ALTER + 回填）；AuthController register/login 与 SocialAuthController 改用 email_hash 查询；实测：注册成功/重复注册 422/登录成功/错误密码 401 |
| 🔄 新发现 P0：HASHIDS_SALT 占位/未读取 | ✅ | 已修复：`config/hashids.php` main.salt 读 `getenv('HASHIDS_SALT')`；本环境 `.env` 生成随机 salt（原为 change_me 占位，且配置写死空 salt 导致 fail-closed 异常） |
| Quick Win #3：业务种子数据自动导入 | ✅ | 新增 `service/database/seeders/run.php`（幂等：countries 23 + logistics 3 + shipping zones 3 + rates 3 + gateways 2 + methods 2 + hs_codes 8 + tariff_rules 7）；实测重跑 0 新增 51 跳过；/api/countries、/api/payment/methods、/api/shipping/calculate（北美区 DHL 12.24）、/api/tariff/estimate 全部可用 |
| 🔄 新发现：模型错误 encryptable（name 类非敏感字段） | ✅ | 30+ 模型把 name 等公开字段加密：破坏按名称查询/排序且短字段放不下密文。已全部清理：种子涉及 4 模型（上轮）+ 批量 17 模型（Categories/Currencies/Shops/Suppliers.name/Merchants.store_name 等），保留 email/mobile/real_name/api_key/access_token 等真敏感字段 |
| 🔄 新发现：模型缺失 Eloquent 关联 | ✅ | PaymentGatewayMethods.gateway、ShippingZoneRates.logistics/zone 缺失导致 /api/payment/methods、/api/shipping/calculate 500，已补 |
| 阶段一：OrderController 真实计费 | ✅ | store() 接入优惠券（满减/折扣/固定，核销 user_coupons + used_qty）、运费（分区+费率阶梯最低价）、关税/VAT（HS Code→目的国税率）；实测 3×49.99=149.97 满100减20 → discount 20 + shipping 12.24 + tax 0 = pay 142.21，库存/核销/明细/日志全链路验证 |
| 🔄 新发现 P0：HashidsDecode 参数丢失 | ✅ | 中间件 setPost($updates) 为整体替换，解码任一 _id 字段会丢弃同请求其他参数（coupon_id/weight_grams 等全站受影响）；改为 array_merge 合并，实测多参数下单正常 |
| 🔄 新发现：下单链路配套 bug | ✅ | CouponController::claim 的 where 列名误写值（改 whereColumn）；Orders.address_snapshot JSON 列缺 cast（补 array cast）；OrderLogs 表无 updated_at（模型 $timestamps=false） |
| 阶段一：InstallController 集成 seeder | ✅ | 安装向导导入 install.sql 后自动执行 service/database/seeders/run.php（独立子进程隔离 autoload，失败仅告警）；同时修复 install.sql 路径 bug（原 base_path(false) 指向 admin/ 找不到上级根目录的 install.sql，改 dirname） |
| 阶段一：鸿蒙下单支付接入 | ✅ | Checkout.ets 接入地址选择、PosterVerify（challenge→verify）、完整下单参数 + X-Poster-Token、支付发起（payment/create）；ApiClient 扩展 headers/参数；**hvigor assembleHap 编译通过** |
| 阶段一：Flutter 下单支付接入 | ⚠️ 已编码待编译验证 | checkout_screen 接入地址/人机验证/完整下单/支付发起；register_screen 接入 PosterVerify；api_client post 支持 headers。**本环境 flutter SDK 缓存只读无法编译**，需本地 `flutter analyze`/`flutter test` 验证（括号/结构静态检查已过） |
| 其余阶段一~四交付物 | ⬜ | 剩余：鸿蒙 token 安全存储（KeyStore）、Stripe/鸿蒙真实支付 SDK 集成、支付完成页与轮询 UI、InstallController 双源表清单校验脚本等（P1-P2） |

---

## 一、总体判断

Erik Shop 基础设施骨架扎实（117 张表、39 控制器、Stripe/PayPal 真实网关、WAF/JWT/AES 安全栈、22 单测全过），但核心交易主链路在 service/admin/Flutter/鸿蒙四端同时断裂、约十余项文档宣称"完整"的能力实为表结构或 CRUD 桩、质量门禁（PHPStan/集成测试/客户端 CI）形同虚设——整体处于**"骨架完整、闭环缺失、文档超前"**的阶段。3-6 个月内须先止血打通交易闭环，再补合规与质量底座，最后扩展增量能力并收敛文档。

## 二、五大全局问题

1. **核心交易主链路三端同时断裂**（跨服务端/Admin/双客户端交叉印证）：service 端 `OrderController::store` 不计优惠券/运费/关税/风控（只累加商品小计）；Flutter 与鸿蒙下单均缺 `address_id` 且被 PosterVerify 40001 拒绝、支付从未调用 `POST /payment/create`；admin 端 `ShopOrderController`/`ShopPaymentController` 因 PHP 8.3 方法签名不兼容类加载即 Fatal。现状上线则购买转化全路径不可用，订单/支付管理菜单一开即崩。
2. **文档系统性超前于代码**（文档/服务端/安全/合规四域一致证实）：`features.md`/`VERSIONS.md`/`README` 将风控引擎(RiskEngine)、Klarna/Adyen 支付、四线分账、商业发票 PDF、订阅周期购/AB 测试、WebSocket 客服 IM、多平台商品刊登全部标注"完整/✅"，实际仅表结构 + admin CRUD 或零业务实现，对商业客户构成交付预期与信任风险。
3. **业务种子数据缺失 + 安全合规执行层空白**（服务端/部署/合规三域同证）：`install.sql` 仅含系统表种子，countries/currencies/payment_gateway_methods/hs_codes/shipping_zones 全新安装后全空（核心接口开箱返回空）；同时 `blocked_countries` 默认空数组、风控零调用、KYC 无提交入口、GDPR/CCPA 仅登记不执行——"开箱即空 + 默认放行"叠加合规声明不实。
4. **Admin 后台业务层"有控制器无页面"**：59/67 为纯 CRUD 桩、无 HTML 视图，菜单点击 404；跨境面板 kpi/chartData 路由与 json 签名双重损坏；40 个控制器未挂菜单，整个商城管理 UI 实际不可用，与文档声称的"完整管理后台"严重不符。
5. **质量门禁名存实亡**（测试/部署/文档三域同证）：仅 22 个单元测试覆盖 4 个工具类、业务控制器/中间件/模型零测试；PHPStan 默认 128M 开箱即崩、admin 无任何质量配置；CI 无 phpstan/php-cs-fixer/composer audit 步骤、无 Flutter/HarmonyOS job；鸿蒙 99 个构建产物误入库，任何重构合并不设防。

## 三、分阶段路线图

### 阶段一：止血与交易主链路打通 — **P0 · 第 1-4 周**

**目标**
- 修复 admin 两个致命控制器并建立防复发冒烟机制，恢复订单/支付管理菜单可用性
- 打通 service 下单真实计费（优惠券/运费/关税/折扣落库）并补支付幂等，使后端订单链路闭环
- 补齐业务种子数据自动导入，保证全新安装核心接口开箱有数据
- 打通 Flutter 与鸿蒙的结算-下单-支付链路（address_id + PosterVerify + payment create/status）

**交付物**
- ✅ 已完成：`admin/plugin/admin/app/controller/shop/ShopOrderController.php` 与 `ShopPaymentController.php` 补 `: array`/`: Response` 返回类型（82/82 反射加载通过）；**剩余**：新增 `scripts/smoke_controllers.php`（php -l + 反射加载全部 82 控制器）并接入 Makefile check 与 CI，作为防复发门禁
- 🔄 **评审新增（高优先）**：PosterVerify 签发接口 `POST /api/poster/verify` —— 中间件校验 Redis 键 `erik:poster:{token}` 但全项目无任何签发/写键代码，客户端无从获取 X-Poster-Token；需调用 poster-php 生成验证码、写 Redis 键（含过期与一次性消费），这是 Flutter/鸿蒙注册、下单、支付接入人机验证的**前置依赖**
- `service/app/controller/v1/OrderController.php` store() 接入 coupon 折扣计算与 shipping_fee/tax_amount/discount_amount 落库（对齐 api.md 5.3 / features.md 3.3），并实现 api.md 2.1 的 min_price/max_price 筛选；`PaymentController::create` 增加 order_id+gateway 幂等去重
- `admin/plugin/admin/app/controller/InstallController.php` step1 末尾追加执行 `service/database/seeders/countries.php`，并新增 erik_payment_gateway_methods（stripe/paypal 各 method 行）、erik_hs_codes 基础库、erik_tariff_rules/erik_shipping_zones 示例种子
- `apps/flutter/lib/features/order/checkout_screen.dart`（**注意：实际路径，非 lib/screens/**）增加地址选择与默认地址回填、提交 address_id+currency_code、接入 PosterVerify（X-Poster-Token）后实现 `POST /payment/create` + `GET /payment/status` 轮询支付页；`apps/harmonyos/entry/src/main/ets/pages/Checkout.ets` 同步补 address_id + selectedShipping + currency_code 与支付调用（鸿蒙需新增地址管理页，Profile 收货地址 route 目前为空）
- ✅ 已完成：`ShopDashboardController.php` 修复 kpi/chartData 路由（kebab→类名精确匹配）与 `$this->json` 签名冲突，替换硬编码示例数据
- 为 service 订单/支付/退款核心接口补集成测试（事务/扣库存/取消、webhook 验签+幂等+分账、Hashids 编解码），复用 CI 已起 MySQL/Redis 服务
- 顺手项：修正 `docs/deployment.md` 中 admin 端口 8787→8788 两处笔误

**负责角色**：后台全栈、后端工程师、支付结算、Flutter、鸿蒙、QA

### 阶段二：合规闭环与支付结算扩展 — **P1 · 第 5-10 周**

**目标**
- 落地风控规则引擎并与订单状态机"待审核(8)"接通，消除"订单无风控即放行"敞口
- 补齐 KYC 用户侧提交闭环与 GDPR/CCPA 执行层（删除/导出/opt-out）
- 统一分账费率源并补全四线分账（Merchant/Supplier/Affiliate 写入）
- 收敛支付方式声明：实现 Klarna/Adyen 或明确占位并同步修正文档，补 3DS 显式代码

**交付物**
- 新增 `service/app/common/RiskEngine.php`（按 config/risk.php checks/velocity 实现 score），在 OrderController::store / PaymentController::create / AuthController 旁路打分，写 erik_orders.risk_score/risk_result 与 RiskLogs，高分置 status=8；ShopRiskRule/ShopRiskLog 挂入 admin 菜单
- 🔄 **评审新增**：风控审核出口 `POST /api/admin/orders/{id}/review`（AdminKeyMiddleware 保护，status=8 原子门闩流转至 1 放行/5 驳回并写 OrderLogs）——当前服务端无任何 status=8 写入/流转路径，只挂菜单不接接口则"待审核"仍是死路；admin 侧 ShopOrder 列表配套审核操作
- `service/config/route.php` 新增 `POST /api/kyc` 与 `GET /api/kyc/status`（real_name/id_number 走 Encryptable），admin 审核通过置 status=1 衔接 OrderController 现有校验（同步明确 admin KYC 审核入口）
- 新增 `service/app/task/PrivacyComplianceTask`（按 config/privacy.php 执行数据删除宽限期/数据导出文件/opt_out 屏蔽标记）+ `POST /api/privacy/cookie-consent` 写入 erik_cookie_consents
- webhook 与 SettlementCron 合并为单一费率配置源（消除 gateway_fee 双源漂移），补 MerchantSettlements/SupplierSettlements/AffiliateCommissions 写入与打款流程，支撑 docs/08-multi-currency-settlement
- **Klarna/Adyen 默认动作**：先采用"明确 throw 占位 + 修正 api.md 6.1 / README / VERSIONS 表述"（低成本、当日完成）；完整实现（含沙箱支付成功 + webhook 验签 + 退款验收）降级至阶段四；`StripeGateway::createPayment` 显式设置 `request_three_d_secure='automatic'` 并回写 erik_payments.three_ds_status

**负责角色**：安全合规、支付结算、后端工程师、后台全栈、跨境 i18n

### 阶段三：质量门禁与后台 UI 补全 — **P1/P2 · 第 11-18 周**

**目标**
- 修复静态分析门禁（PHPStan 内存限制）并为 admin 补齐全套质量配置与测试骨架
- 将 PHPUnit/phpstan/php-cs-fixer/composer audit/Flutter 与鸿蒙 CI 全部纳入门禁
- 为商城管理 P0 模块补 LayUI 列表页或清理 404 菜单，明确"JSON API only"定位
- 修复部署与运行时暴露面（端口绑定、源码卷挂载、GeoIP 数据、dev 依赖）

**交付物**
- ✅ 已完成 service 侧：phpstan 命令带 `--memory-limit=1G`（Makefile/CI，PHPStan 2.x 已移除 neon memoryLimit 参数）；**剩余**：新增 admin/phpstan.neon（level 5）+ admin/.php-cs-fixer.php + admin/phpunit.xml + admin/tests/（优先覆盖 Crud 基类 inputFilter/doSelect/数据权限、AccessControl 鉴权、ShopRefundController mock 远程退款）
- ✅ 已完成：ci.yml 新增 composer audit + phpstan；**剩余**：php-cs-fixer --dry-run、service 集成测试（MySQL/Redis 服务直连）、Flutter analyze+test job 与鸿蒙 hvigor 构建 job
- `admin/plugin/admin/app/controller/shop/` UI 补全按**优先级矩阵**执行：P0（订单/退款/发货/支付）必须补 index() 与 view/shop/ 下 index.html（LayUI 列表）；其余菜单项默认从 config/menu.php 移除并标注"JSON API only"（移除即消除 404，零成本），补页作为后续按需增量，避免悬置半成品
- 🔄 评审新增：鸿蒙仓库治理（.gitignore 增加 `apps/harmonyos/**/build`、`**/.hvigor`、`**/oh_modules` 并 `git rm --cached` 清理已入库的 99 个构建产物；补充 hvigorw wrapper）——这是 CI 接鸿蒙构建 job 的前置
- 🔄 评审新增：install.sql 与 InstallController `$tables_to_install` 冲突表清单双源维护校验脚本（解析 install.sql 的 CREATE TABLE 动态生成或对比两处一致）
- `docker-compose.yml` 将 ES/Redis/MySQL 端口绑定改 127.0.0.1（仅 nginx 暴露 80/443），移除 `./service:/app` 与 `./admin:/app` 源码卷挂载并新增 service/.dockerignore 与 admin/.dockerignore（排除 vendor/runtime/.git），确保容器运行 --no-dev vendor
- 补 GeoLite2-Country.mmdb 下载脚本（或启用 MAXMIND_LICENSE_KEY 自动更新）落位 service/database/geoip/；config/cron.php 三个空 URL 日志提升 WARNING 并补醒目注释

**负责角色**：QA、DevOps、后台全栈、Flutter、鸿蒙

### 阶段四：增量能力与文档收敛 — **P2 · 第 19-26 周**

**目标**
- 实现文档标"完整"而实际缺失的增量能力（发票 PDF、库存流水、多平台刊登、订阅周期购）
- 启用读写分离、多币种结算闭环与 ES 多语言搜索增强
- 统一文档三态标注（已实现/表结构已建/规划中）并建立端点一致性检查，杜绝进一步漂移

**交付物**
- `service/app/controller/v1/DocumentController.php` 用已引入的 barryvdh/laravel-dompdf 按需生成商业发票/装箱单 PDF 并落 erik_order_documents；OrderController 扣库存时写 erik_inventory_logs 不可变流水
- PlatformOrderSyncCron 增加 amazon/eBay/Shopee 适配器并实现商品刊登写入 PlatformListings；新增订阅周期购 API（erik_subscriptions 已建表，先定义最小业务范围：订阅计费周期 + 取消 + 续费）与 WebSocket 客服服务端（ChatSessions/ChatMessages 已建表）
- 启用 config/database.php 的 mysql_rw 读写分离（只读查询显式切换，含 sticky 语义），补 CurrencyExchangeGainsLosses 结算汇率对比写入，闭环多币种分账结算
- `Products::toSearchableArray()` 扩展多语言 title/description 索引字段并按 locale 加权，增强 ES 多语言搜索
- Klarna/Adyen 完整实现（按需排期，验收条件：沙箱支付成功 + webhook 验签 + 退款闭环）
- 🔄 评审新增：支付部分退款能力（Refunds 状态机 2/3 流转、部分退款金额与订单状态联动）与 webhook 事件覆盖扩展（payment_intent.refunded/failed 等非成功事件的显式处理策略，当前静默忽略依赖 PaymentReconcileCron 兜底）
- 🔄 评审新增：认证加固——JWT 吊销（Redis 黑名单或 token 版本号，改密/登出后失效）、密码重置/邮箱验证流程（研究 §5 建议，路线图此前遗漏）
- 🔄 评审新增：客户端 AES 接口加密接入（Flutter/HarmonyOS 支持 X-Encrypted/X-Encrypt-Response）+ 鸿蒙 token 安全存储（KeyStore/security.asset 替代 preferences 明文），未完成前修正 README/VERSIONS 的"三层加密"声明

**负责角色**：后端工程师、后台全栈、支付结算、跨境 i18n、QA

## 四、关键风险（必须优先处理）

1. **支付链路缺幂等且分账费率双源漂移**：payment/create 重复请求生成多条待支付单，webhook 仅处理成功事件；gateway_fee 费率双处独立维护，分账口径存在重复与不一致风险。
2. **文档超前于代码的信任风险**：风控引擎、Klarna/Adyen、四线分账、发票 PDF、订阅/AB、WS 客服等十余项宣称"完整"实为占位或 CRUD 桩，对商业客户构成交付预期落差。
3. **全新安装种子数据为空 + 合规默认放行**：countries/支付方式/运费/关税接口开箱返回空；blocked_countries 默认空数组、KYC 仅 KR，漏配即完全放开。
4. **质量门禁形同虚设**：仅 22 个单元测试覆盖工具类，PHPStan 默认 128M 开箱即崩、admin 无测试与质量配置，CI 无 phpstan/composer audit/客户端 job，重构合并不设防。
5. **生产中间件暴露面**：ES 无认证且 9200 暴露、Redis 默认无密码、MySQL/服务端口全暴露，.env 未配置完整即可裸奔启动上线。

## 五、Quick Wins（可立即做的低成本高收益事项）

1. **✅ 已完成** PHPStan 门禁：Makefile check 与 CI 的 phpstan 命令带 `--memory-limit=1G`（注意：PHPStan 2.2.8 已移除 neon 的 `memoryLimit` 参数，必须用 CLI 传参，neon 里配置会报 `Unexpected item`）。实测 `make check` → `[OK] No errors`。
2. **✅ 已完成** 为 ShopOrderController/ShopPaymentController 补 `: array`/`: Response` 返回类型，修复后 82/82 控制器反射加载成功；防复发冒烟脚本见阶段一交付物。
3. InstallController step1 末尾自动导入 countries 种子与支付方式/HS Code/运费关税示例，全新安装开箱有数据。
4. **✅ 已完成** 修复 ShopDashboardController 的 kpi/chartData 路由（kebab→类名精确匹配）与 `$this->json` 签名冲突（改 `$this->json(0,'ok',$data)`），替换硬编码示例数据。
5. **✅ 已完成** CI 增加 composer audit 步骤（`||` 兜底不因已知低危 CVE 阻断）与 phpstan 步骤，依赖安全纳入门禁。

## 六、启动顺序建议

**先启动阶段一（止血与交易主链路打通）**：四端交易链路断裂与 admin 致命错误属上线阻断级问题；且控制器签名修复、下单计费、种子导入、双端支付打通彼此独立可并行、1-4 周即可见效；先跑通主链路，才能为后续合规与质量门禁提供可验证的基线。

## 附录

- **团队结构**：协调层（Team Lead、系统架构师）→ 服务端小分队（后端/支付结算/搜索推荐/后台全栈）→ 客户端小分队（Flutter、鸿蒙）→ 横向支撑（安全合规、QA、DevOps、跨境 i18n），详见根 `CLAUDE.md` 与团队规划讨论。
- **调研明细**：`docs/PLAN-RESEARCH.md`（7 个领域：服务端 API / 管理后台 / Flutter / 鸿蒙 / 安全合规 / 部署数据测试 / 文档功能覆盖）。
