# 跨境电商平台 — 功能设计文档

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 功能覆盖

| 维度 | 功能 |
|------|------|
| B2C零售 | 多语言商品/分币种定价/SKU/购物车/订单/支付/退款/退货 |
| B2B批发 | 阶梯定价(MOQ)/企业认证/询价 |
| 多商家 | 卖家审核/商品审核/分成分账 |
| 跨境合规 | HS Code/关税规则/VAT/IOSS/合规标签(FDA/CE/RoHS) |
| 国际物流 | 分区运费/海外仓/HS申报/商业发票/装箱单 |
| 支付 | Stripe/PayPal/Klarna/Adyen + BNPL + 3DS |
| 营销 | 优惠券/轮播图/秒杀/拼团/分销 |
| 多平台 | Amazon/eBay/Shopee刊登+订单聚合 |
| 供应链 | 供应商/采购/质检/库存流水/调拨 |
| 风控 | 规则引擎/KYC/GDPR/CCPA/Cookie Consent |
| 安全 | 15类攻击检测(XSS/SQL注入/XXE/SSRF等) |
| 高并发 | 限流/Cache-Aside/防雪崩/防穿透/熔断/读写分离 |
| 会员 | 积分/等级/礼品卡/降价提醒/订阅/AB测试 |
| 多端 | Flutter(5平台)+HarmonyOS+Web Admin |
| 国际化 | 5语言翻译 + LocaleMiddleware + Flutter AppLocalizations |
| 平台追踪 | 8平台 X-Platform header + 6表DB记录 |
| API文档 | hg/apidoc: Service(6分组23方法) + Admin(10分组15控制器) |
| 测试 | 23 tests / 68 assertions PASS |
