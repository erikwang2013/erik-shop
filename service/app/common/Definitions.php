<?php
namespace app\common;

/**
 * @Apidoc\Definition("id", type="string", desc="资源ID(hashids编码)")
 * @Apidoc\Definition("page", type="int", default=1, desc="页码")
 * @Apidoc\Definition("per_page", type="int", default=20, desc="每页数量")
 * @Apidoc\Definition("keyword", type="string", desc="搜索关键词")
 * @Apidoc\Definition("email", type="string", desc="邮箱")
 * @Apidoc\Definition("password", type="string", desc="密码")
 * @Apidoc\Definition("access_token", type="string", desc="JWT令牌")
 * @Apidoc\Definition("expires_in", type="int", desc="有效期(秒)")
 * @Apidoc\Definition("currency_code", type="string", default="USD", desc="币种")
 * @Apidoc\Definition("amount", type="float", desc="金额")
 * @Apidoc\Definition("quantity", type="int", desc="数量")
 * @Apidoc\Definition("gateway", type="string", desc="支付网关")
 * @Apidoc\Definition("order_no", type="string", desc="订单号")
 * @Apidoc\Definition("sku_id", type="string", desc="SKU ID")
 * @Apidoc\Definition("product_id", type="string", desc="商品ID")
 * @Apidoc\Definition("category_id", type="string", desc="分类ID")
 * @Apidoc\Definition("order_id", type="string", desc="订单ID")
 * @Apidoc\Definition("address_id", type="string", desc="地址ID")
 */
class Definitions {}
