<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

/**
 * hg/apidoc 通用注解定义
 *
 * @Apidoc\Definition("id", type="string", desc="资源ID (hashids编码)")
 * @Apidoc\Definition("page", type="int", default=1, desc="页码")
 * @Apidoc\Definition("per_page", type="int", default=20, desc="每页数量")
 * @Apidoc\Definition("keyword", type="string", desc="搜索关键词")
 * @Apidoc\Definition("sort", type="string", desc="排序: default/price_asc/price_desc/sales/newest")
 * @Apidoc\Definition("status", type="int", desc="状态码")
 * @Apidoc\Definition("currency_code", type="string", default="USD", desc="币种代码")
 * @Apidoc\Definition("country_code", type="string", default="US", desc="国家ISO2代码")
 * @Apidoc\Definition("token", type="string", desc="JWT Token")
 * @Apidoc\Definition("email", type="string", desc="邮箱地址")
 * @Apidoc\Definition("password", type="string", desc="密码")
 * @Apidoc\Definition("nickname", type="string", desc="昵称")
 * @Apidoc\Definition("access_token", type="string", desc="访问令牌")
 * @Apidoc\Definition("expires_in", type="int", desc="过期时间(秒)")
 * @Apidoc\Definition("order_no", type="string", desc="订单号")
 * @Apidoc\Definition("amount", type="float", desc="金额")
 * @Apidoc\Definition("quantity", type="int", desc="数量")
 * @Apidoc\Definition("gateway", type="string", desc="支付网关: stripe/paypal/klarna")
 * @Apidoc\Definition("method", type="string", desc="支付方式: card/paypal/klarna_paylater")
 * @Apidoc\Definition("sku_id", type="string", desc="SKU ID (hashids编码)")
 * @Apidoc\Definition("product_id", type="string", desc="商品ID (hashids编码)")
 * @Apidoc\Definition("category_id", type="string", desc="分类ID (hashids编码)")
 * @Apidoc\Definition("order_id", type="string", desc="订单ID (hashids编码)")
 * @Apidoc\Definition("address_id", type="string", desc="地址ID (hashids编码)")
 * @Apidoc\Definition("coupon_id", type="string", desc="优惠券ID (hashids编码)")
 */
class Definitions
{
}
