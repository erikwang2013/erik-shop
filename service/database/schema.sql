-- =============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
--
-- 跨境电商平台 - 完整建表语句
-- 表前缀: erik_
-- 主键: BIGINT（snowflake生成，非自增）
-- 所有表含 created_at / updated_at / deleted_at
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================
-- 模块1: 用户与账户 (7张)
-- =============================================================

CREATE TABLE `erik_users` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT 'snowflake主键',
    `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '昵称',
    `avatar` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '头像URL',
    `email` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '邮箱(加密)',
    `mobile` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '手机号(加密)',
    `password` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '密码hash',
    `salt` CHAR(6) NOT NULL DEFAULT '' COMMENT '密码盐值',
    `sex` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '性别 0未知/1男/2女',
    `birthday` DATE NULL COMMENT '生日',
    `money` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '余额',
    `score` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '积分',
    `level` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '会员等级',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态 0禁用/1正常',
    `last_login_at` DATETIME NULL COMMENT '最后登录时间',
    `last_login_ip` VARCHAR(45) NOT NULL DEFAULT '' COMMENT '最后登录IP',
    `invite_code` VARCHAR(16) NOT NULL DEFAULT '' COMMENT '邀请码(自己)',
    `inviter_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '邀请人ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '注册时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME NULL DEFAULT NULL COMMENT '删除时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_invite_code` (`invite_code`),
    KEY `idx_status` (`status`),
    KEY `idx_inviter_id` (`inviter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

CREATE TABLE `erik_user_addresses` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT 'snowflake主键',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `name` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '收件人(加密)',
    `phone` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '电话(加密)',
    `country_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '国家ID',
    `province` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '省/州',
    `city` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '城市',
    `district` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '区/县',
    `detail` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '详细地址(加密)',
    `postal_code` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '邮编',
    `is_default` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否默认地址',
    `tag` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '地址标签(家/公司)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_country_id` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户地址表';

CREATE TABLE `erik_user_social_accounts` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `provider` VARCHAR(32) NOT NULL COMMENT '社交平台 google/apple/facebook',
    `provider_user_id` VARCHAR(256) NOT NULL COMMENT '平台用户唯一ID',
    `access_token` VARCHAR(1024) NOT NULL DEFAULT '' COMMENT 'access_token(加密)',
    `refresh_token` VARCHAR(1024) NOT NULL DEFAULT '' COMMENT 'refresh_token(加密)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_provider_user` (`provider`, `provider_user_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户社交账号绑定';

CREATE TABLE `erik_user_kyc` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `real_name` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '真实姓名(加密)',
    `id_type` VARCHAR(32) NOT NULL DEFAULT 'id_card' COMMENT '证件类型',
    `id_number` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '证件号码(加密)',
    `id_front_image` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '证件正面照',
    `id_back_image` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '证件背面照',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态 0待审/1通过/2驳回',
    `verified_at` DATETIME NULL COMMENT '认证通过时间',
    `reject_reason` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '驳回原因',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_id` (`user_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户实名认证';

CREATE TABLE `erik_user_wishlists` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_product` (`user_id`, `product_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户收藏夹';

CREATE TABLE `erik_membership_levels` (
    `id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(64) NOT NULL COMMENT '等级名称',
    `level` TINYINT UNSIGNED NOT NULL COMMENT '等级序号',
    `min_score` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所需最低积分',
    `icon` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '等级图标',
    `discount_rate` DECIMAL(4,2) NOT NULL DEFAULT 0.00 COMMENT '折扣率%',
    `free_shipping` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否免运费',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会员等级定义';

CREATE TABLE `erik_membership_benefits` (
    `id` BIGINT UNSIGNED NOT NULL,
    `level_id` BIGINT UNSIGNED NOT NULL COMMENT '等级ID',
    `benefit_type` VARCHAR(32) NOT NULL COMMENT '权益类型 discount/free_shipping/priority/birthday_gift',
    `benefit_value` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '权益值',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_level_id` (`level_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会员权益配置';


-- =============================================================
-- 模块2: 商品与分类 (16张)
-- =============================================================

CREATE TABLE `erik_categories` (
    `id` BIGINT UNSIGNED NOT NULL,
    `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '父分类ID',
    `name` VARCHAR(128) NOT NULL COMMENT '分类名称',
    `slug` VARCHAR(128) NOT NULL DEFAULT '' COMMENT 'URL别名',
    `icon` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '图标',
    `image` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '分类图片',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态 0隐藏/1显示',
    `is_hot` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否热门',
    `level` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '层级',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_sort` (`sort`),
    KEY `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分类表';

CREATE TABLE `erik_products` (
    `id` BIGINT UNSIGNED NOT NULL,
    `category_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '分类ID',
    `title` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '商品标题(默认语言)',
    `subtitle` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '副标题',
    `slug` VARCHAR(256) NOT NULL DEFAULT '' COMMENT 'URL别名',
    `brand` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '品牌',
    `main_image` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '主图',
    `video_url` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '视频URL',
    `description` TEXT COMMENT '描述(默认语言)',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0草稿/1待审/2已上架/3已下架',
    `is_hot` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '热门',
    `is_new` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '新品',
    `is_recommend` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '推荐',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序',
    `sales_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '销量',
    `view_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '浏览量',
    `min_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '最低价(默认币种)',
    `max_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '最高价(默认币种)',
    `weight` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '重量(克)',
    `unit` VARCHAR(16) NOT NULL DEFAULT 'piece' COMMENT '单位',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_category_id` (`category_id`),
    KEY `idx_status` (`status`),
    KEY `idx_sort` (`sort`),
    KEY `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品主表';

CREATE TABLE `erik_product_translations` (
    `id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `locale` VARCHAR(10) NOT NULL COMMENT '语言 zh_CN/zh_HK/en/ja/ko',
    `title` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '标题',
    `subtitle` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '副标题',
    `description` TEXT COMMENT '描述',
    `meta_title` VARCHAR(256) NOT NULL DEFAULT '' COMMENT 'SEO标题',
    `meta_description` VARCHAR(512) NOT NULL DEFAULT '' COMMENT 'SEO描述',
    `meta_keywords` VARCHAR(256) NOT NULL DEFAULT '' COMMENT 'SEO关键词',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_product_locale` (`product_id`, `locale`),
    KEY `idx_locale` (`locale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品多语言内容';

CREATE TABLE `erik_product_skus` (
    `id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `sku_code` VARCHAR(64) NOT NULL COMMENT 'SKU编码',
    `attrs` JSON COMMENT '属性 {"color":"Red","size":"XL"}',
    `default_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '默认价格(CNY)',
    `origin_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '原价',
    `cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '成本价',
    `stock` INT NOT NULL DEFAULT 0 COMMENT '总库存',
    `stock_warning` INT UNSIGNED NOT NULL DEFAULT 10 COMMENT '库存预警值',
    `image` VARCHAR(512) NOT NULL DEFAULT '' COMMENT 'SKU图片',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0下架/1上架',
    `sales_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '销量',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_sku_code` (`sku_code`),
    KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SKU变体';

CREATE TABLE `erik_product_sku_prices` (
    `id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL,
    `currency_code` CHAR(3) NOT NULL COMMENT '币种 USD/EUR/GBP/JPY/KRW',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '该币种价格',
    `origin_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '该币种原价',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_sku_currency` (`sku_id`, `currency_code`),
    KEY `idx_sku_id` (`sku_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SKU分币种定价';

CREATE TABLE `erik_product_images` (
    `id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `url` VARCHAR(512) NOT NULL,
    `sort` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_main` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品图片';

CREATE TABLE `erik_product_attrs` (
    `id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(64) NOT NULL COMMENT '属性名 颜色/尺寸/材质',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_size_related` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否尺码属性',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品属性定义';

CREATE TABLE `erik_product_attr_values` (
    `id` BIGINT UNSIGNED NOT NULL,
    `attr_id` BIGINT UNSIGNED NOT NULL,
    `value` VARCHAR(128) NOT NULL,
    `sort` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_attr_id` (`attr_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品属性值';

CREATE TABLE `erik_product_reviews` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `rating` TINYINT UNSIGNED NOT NULL COMMENT '评分 1-5',
    `content` TEXT COMMENT '评价内容',
    `images` JSON COMMENT '评价图片',
    `is_anonymous` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0隐藏/1显示',
    `reply_content` TEXT COMMENT '商家回复',
    `reply_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品评价';

CREATE TABLE `erik_review_translations` (
    `id` BIGINT UNSIGNED NOT NULL,
    `review_id` BIGINT UNSIGNED NOT NULL,
    `locale` VARCHAR(10) NOT NULL,
    `content` TEXT COMMENT '翻译后的评价内容',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_review_locale` (`review_id`, `locale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='评价翻译缓存';

CREATE TABLE `erik_product_compliance` (
    `id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `compliance_category_id` BIGINT UNSIGNED NOT NULL COMMENT '合规分类ID',
    `cert_no` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '证书编号',
    `cert_file` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '证书文件',
    `expire_at` DATE NULL COMMENT '证书过期日',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_product_compliance` (`product_id`, `compliance_category_id`),
    KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品合规标签';

CREATE TABLE `erik_compliance_categories` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(32) NOT NULL COMMENT '代码 FDA/CE/RoHS/FCM/COSMETIC/TOY/ELECTRONIC/CHILDREN/TEXTILE/BATTERY',
    `name` VARCHAR(128) NOT NULL COMMENT '名称',
    `description` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '说明',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='合规分类';

CREATE TABLE `erik_product_hs_codes` (
    `id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `hs_code_id` BIGINT UNSIGNED NOT NULL COMMENT 'HS编码ID',
    `is_primary` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_hs_code_id` (`hs_code_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品HS Code关联';

CREATE TABLE `erik_banners` (
    `id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '标题',
    `image` VARCHAR(512) NOT NULL COMMENT '图片URL',
    `link_url` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '跳转链接',
    `position` VARCHAR(32) NOT NULL DEFAULT 'home' COMMENT '位置 home/category/product',
    `countries` JSON COMMENT '可见区域 ["US","DE","JP"] null=全部',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `start_at` DATETIME NULL COMMENT '开始时间',
    `end_at` DATETIME NULL COMMENT '结束时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_position_status` (`position`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='轮播图表';

CREATE TABLE `erik_product_comparisons` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品对比记录';

CREATE TABLE `erik_product_recommendations` (
    `id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL COMMENT '源商品',
    `recommended_product_id` BIGINT UNSIGNED NOT NULL COMMENT '推荐商品',
    `type` VARCHAR(32) NOT NULL DEFAULT 'collaborative' COMMENT '推荐类型 collaborative/viewed/related',
    `score` DECIMAL(6,4) NOT NULL DEFAULT 0.0000 COMMENT '推荐评分',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_product_type` (`product_id`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品推荐关联';

-- =============================================================
-- 模块3: 购物车与订单 (9张)
-- =============================================================

CREATE TABLE `erik_carts` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `selected` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '是否选中',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_sku` (`user_id`, `sku_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='购物车';

CREATE TABLE `erik_orders` (
    `id` BIGINT UNSIGNED NOT NULL,
    `order_no` VARCHAR(32) NOT NULL COMMENT '订单号',
    `user_id` BIGINT UNSIGNED NOT NULL,
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待付款/1已付款/2已发货/3已收货/4已完成/5已取消/6退款中/7已退款/8待审核',
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '商品总金额',
    `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '优惠金额',
    `shipping_fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '运费',
    `insurance_fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '保险费',
    `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '税费',
    `pay_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '实付金额',
    `currency_code` CHAR(3) NOT NULL DEFAULT 'USD' COMMENT '支付币种',
    `exchange_rate` DECIMAL(10,6) NOT NULL DEFAULT 1.000000 COMMENT '支付时汇率',
    `pay_method` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '支付方式 stripe/paypal/klarna',
    `pay_at` DATETIME NULL COMMENT '支付时间',
    `shipping_at` DATETIME NULL COMMENT '发货时间',
    `received_at` DATETIME NULL COMMENT '收货时间',
    `canceled_at` DATETIME NULL COMMENT '取消时间',
    `risk_score` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '风控分数',
    `risk_result` VARCHAR(16) NOT NULL DEFAULT 'pass' COMMENT '风控结果 pass/warn/review',
    `remark` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '订单备注',
    `address_snapshot` JSON COMMENT '地址快照',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order_no` (`order_no`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_pay_at` (`pay_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单主表';

CREATE TABLE `erik_order_items` (
    `id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(256) NOT NULL COMMENT '商品标题(下单时快照)',
    `image` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '商品图片',
    `sku_attrs_snapshot` JSON COMMENT 'SKU属性快照',
    `price` DECIMAL(12,2) NOT NULL COMMENT '单价(支付币种)',
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `subtotal` DECIMAL(12,2) NOT NULL COMMENT '小计',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单明细';

CREATE TABLE `erik_order_logs` (
    `id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `from_status` TINYINT NOT NULL DEFAULT -1,
    `to_status` TINYINT NOT NULL,
    `operator` VARCHAR(64) NOT NULL DEFAULT 'system' COMMENT '操作者 user/admin/system',
    `remark` VARCHAR(256) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单状态日志';

CREATE TABLE `erik_payments` (
    `id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `gateway` VARCHAR(32) NOT NULL COMMENT '支付网关 stripe/paypal/klarna/adyen',
    `method` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '支付方式 card/ideal/klarna_paylater',
    `transaction_no` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '网关交易号',
    `amount` DECIMAL(12,2) NOT NULL COMMENT '支付金额',
    `currency_code` CHAR(3) NOT NULL DEFAULT 'USD',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待支付/1已支付/2已退款/3失败',
    `three_ds_status` VARCHAR(16) NOT NULL DEFAULT '' COMMENT '3DS状态',
    `paid_at` DATETIME NULL,
    `refunded_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已退款金额',
    `gateway_data` JSON COMMENT '网关原始数据',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_transaction_no` (`transaction_no`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='支付记录';

CREATE TABLE `erik_refunds` (
    `id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `refund_no` VARCHAR(32) NOT NULL COMMENT '退款单号',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1仅退款/2退货退款',
    `amount` DECIMAL(12,2) NOT NULL COMMENT '退款金额',
    `reason` VARCHAR(256) NOT NULL DEFAULT '',
    `images` JSON COMMENT '凭证图片',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待审/1通过/2驳回/3已退款',
    `reject_reason` VARCHAR(256) NOT NULL DEFAULT '',
    `refunded_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_refund_no` (`refund_no`),
    KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='退款记录';

CREATE TABLE `erik_return_orders` (
    `id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `return_no` VARCHAR(32) NOT NULL COMMENT '退货单号',
    `reason_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '退货原因ID',
    `type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1当地仓/2退回国内/3仅退款',
    `return_warehouse_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '退回仓库ID',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待审/1通过/2已寄回/3已收货/4已完成/5驳回',
    `remark` VARCHAR(512) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_return_no` (`return_no`),
    KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='退货单';

CREATE TABLE `erik_return_labels` (
    `id` BIGINT UNSIGNED NOT NULL,
    `return_id` BIGINT UNSIGNED NOT NULL,
    `logistics_id` BIGINT UNSIGNED NOT NULL COMMENT '物流商ID',
    `tracking_no` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '追踪号',
    `label_url` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '面单URL',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_return_id` (`return_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='退货面单';

CREATE TABLE `erik_order_documents` (
    `id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `type` VARCHAR(32) NOT NULL COMMENT 'invoice/packing_list/certificate_of_origin',
    `file_path` VARCHAR(512) NOT NULL COMMENT 'PDF文件路径',
    `generated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='清关单据';

-- =============================================================
-- 模块4: 国家/货币/物流 (11张)
-- =============================================================

CREATE TABLE `erik_countries` (
    `id` BIGINT UNSIGNED NOT NULL,
    `name_en` VARCHAR(128) NOT NULL COMMENT '英文名',
    `name_cn` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '中文名',
    `iso_code_2` CHAR(2) NOT NULL COMMENT 'ISO 3166-1 alpha-2',
    `iso_code_3` CHAR(3) NOT NULL DEFAULT '' COMMENT 'ISO 3166-1 alpha-3',
    `phone_code` VARCHAR(8) NOT NULL DEFAULT '' COMMENT '电话区号',
    `currency_code` CHAR(3) NOT NULL DEFAULT 'USD' COMMENT '默认币种',
    `flag_emoji` VARCHAR(8) NOT NULL DEFAULT '' COMMENT '旗帜emoji',
    `timezone` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '时区',
    `price_display_mode` ENUM('tax_inclusive','tax_exclusive','both') NOT NULL DEFAULT 'tax_exclusive' COMMENT '价格展示方式',
    `kyc_required` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否要求KYC',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `sort` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_iso_code_2` (`iso_code_2`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国家/地区';

CREATE TABLE `erik_currencies` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` CHAR(3) NOT NULL COMMENT '币种代码 USD/EUR/GBP/JPY/KRW/CNY',
    `name` VARCHAR(64) NOT NULL COMMENT '币种名称',
    `symbol` VARCHAR(8) NOT NULL DEFAULT '' COMMENT '符号 $/€/£/¥',
    `is_default` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='货币定义';

CREATE TABLE `erik_exchange_rates` (
    `id` BIGINT UNSIGNED NOT NULL,
    `from_currency` CHAR(3) NOT NULL,
    `to_currency` CHAR(3) NOT NULL,
    `rate` DECIMAL(18,8) NOT NULL COMMENT '汇率',
    `source` VARCHAR(32) NOT NULL DEFAULT 'manual' COMMENT '来源 manual/exchangerate-api',
    `effective_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '生效时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_from_to` (`from_currency`, `to_currency`),
    KEY `idx_effective_at` (`effective_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='汇率表';

CREATE TABLE `erik_logistics_companies` (
    `id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(128) NOT NULL COMMENT '物流商名称 DHL/UPS/FedEx/EMS',
    `code` VARCHAR(32) NOT NULL COMMENT '物流商代码',
    `tracking_url` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '轨迹查询URL模板',
    `estimated_days` VARCHAR(32) NOT NULL DEFAULT '7-15' COMMENT '预计时效',
    `api_key` VARCHAR(256) NOT NULL DEFAULT '' COMMENT 'API密钥(加密)',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='国际物流商';

CREATE TABLE `erik_shipping_zones` (
    `id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(128) NOT NULL COMMENT '分区名 北美区/西欧区/亚太区',
    `countries` JSON COMMENT '包含国家 ["US","CA","MX"]',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='物流分区';

CREATE TABLE `erik_shipping_zone_rates` (
    `id` BIGINT UNSIGNED NOT NULL,
    `zone_id` BIGINT UNSIGNED NOT NULL,
    `logistics_id` BIGINT UNSIGNED NOT NULL,
    `weight_from` DECIMAL(8,3) NOT NULL DEFAULT 0.000 COMMENT '起始重量(kg)',
    `weight_to` DECIMAL(8,3) NULL COMMENT '结束重量(kg) null=不限',
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '首重价格',
    `per_kg_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '续重单价',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_zone_logistics` (`zone_id`, `logistics_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='物流分区费率阶梯';

CREATE TABLE `erik_warehouses` (
    `id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(128) NOT NULL COMMENT '仓库名',
    `country_id` BIGINT UNSIGNED NOT NULL COMMENT '所在国家',
    `address` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '仓库地址',
    `contact` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '联系人',
    `is_default` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否默认发货仓',
    `is_return_warehouse` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否退货仓',
    `is_overseas` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否海外仓',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_country_id` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='仓库';

CREATE TABLE `erik_shipments` (
    `id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `logistics_id` BIGINT UNSIGNED NOT NULL,
    `warehouse_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '发货仓库',
    `tracking_no` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '物流追踪号',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待发货/1已发货/2运输中/3已签收/4异常',
    `declared_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '申报价值',
    `declared_currency` CHAR(3) NOT NULL DEFAULT 'USD' COMMENT '申报币种',
    `hs_code` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '申报HS编码',
    `origin_country` CHAR(2) NOT NULL DEFAULT 'CN' COMMENT '原产国',
    `package_weight_grams` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '包裹重量(克)',
    `package_description` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '包裹描述',
    `has_battery` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `has_liquid` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `shipped_at` DATETIME NULL,
    `delivered_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_tracking_no` (`tracking_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='发货记录';

CREATE TABLE `erik_shipping_insurances` (
    `id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `shipment_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `declared_value` DECIMAL(12,2) NOT NULL COMMENT '投保金额',
    `premium` DECIMAL(10,2) NOT NULL COMMENT '保费',
    `insurance_no` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '保单号',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待生效/1已生效/2理赔中/3已理赔',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='物流保险';

CREATE TABLE `erik_inventory_logs` (
    `id` BIGINT UNSIGNED NOT NULL,
    `warehouse_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL,
    `type` VARCHAR(16) NOT NULL COMMENT 'inbound/outbound/transfer_in/transfer_out/adjust',
    `quantity` INT NOT NULL COMMENT '变动量(正=入库/负=出库)',
    `balance_after` INT NOT NULL DEFAULT 0 COMMENT '变动后库存',
    `reference_type` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '关联类型 purchase_order/order/return/transfer/adjustment',
    `reference_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联单据ID',
    `operator_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作人ID',
    `remark` VARCHAR(256) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_warehouse_sku` (`warehouse_id`, `sku_id`),
    KEY `idx_reference` (`reference_type`, `reference_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='库存流水(不可变账本)';

CREATE TABLE `erik_inventory_transfers` (
    `id` BIGINT UNSIGNED NOT NULL,
    `from_warehouse_id` BIGINT UNSIGNED NOT NULL,
    `to_warehouse_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待处理/1已发出/2已到达',
    `remark` VARCHAR(256) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_from_warehouse` (`from_warehouse_id`),
    KEY `idx_to_warehouse` (`to_warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='库存调拨';

-- =============================================================
-- 模块5: 海关与税务 (5张)
-- =============================================================

CREATE TABLE `erik_hs_codes` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` CHAR(6) NOT NULL COMMENT 'HS 6位基码',
    `description` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '描述',
    `parent_code` CHAR(4) NOT NULL DEFAULT '' COMMENT '父级4位码',
    `section` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'HS大类',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_parent_code` (`parent_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='HS Code编码库';

CREATE TABLE `erik_tariff_rules` (
    `id` BIGINT UNSIGNED NOT NULL,
    `dest_country_id` BIGINT UNSIGNED NOT NULL COMMENT '目的国',
    `hs_code_id` BIGINT UNSIGNED NOT NULL COMMENT 'HS编码',
    `duty_rate` DECIMAL(6,3) NOT NULL DEFAULT 0.000 COMMENT '关税率%',
    `vat_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '增值税率%',
    `duty_free_threshold` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '关税起征点',
    `vat_free_threshold` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '增值税起征点',
    `remark` VARCHAR(256) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_country_hs` (`dest_country_id`, `hs_code_id`),
    KEY `idx_dest_country_id` (`dest_country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='关税规则';

CREATE TABLE `erik_vat_settings` (
    `id` BIGINT UNSIGNED NOT NULL,
    `country_id` BIGINT UNSIGNED NOT NULL,
    `vat_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '标准VAT税率',
    `reduced_vat_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '优惠VAT税率',
    `ioss_enabled` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否启用IOSS',
    `ioss_number` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'IOSS编号',
    `duty_free_threshold` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '关税起征点',
    `vat_free_threshold` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'VAT起征点',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_country_id` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='各国VAT/IOSS设置';

CREATE TABLE `erik_country_compliance_rules` (
    `id` BIGINT UNSIGNED NOT NULL,
    `country_id` BIGINT UNSIGNED NOT NULL,
    `compliance_category_id` BIGINT UNSIGNED NOT NULL,
    `rule` VARCHAR(16) NOT NULL DEFAULT 'allowed' COMMENT 'allowed/restricted/prohibited',
    `restriction_reason` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '限制原因',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_country_compliance` (`country_id`, `compliance_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='各国合规限制规则';

-- =============================================================
-- 模块6: 支付与资金 (5张)
-- =============================================================

CREATE TABLE `erik_payment_gateways` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(32) NOT NULL COMMENT 'stripe/paypal/klarna/adyen',
    `name` VARCHAR(64) NOT NULL,
    `api_key` VARCHAR(256) NOT NULL DEFAULT '' COMMENT 'API密钥(加密)',
    `api_secret` VARCHAR(256) NOT NULL DEFAULT '' COMMENT 'API密钥(加密)',
    `webhook_secret` VARCHAR(256) NOT NULL DEFAULT '' COMMENT 'Webhook验签密钥(加密)',
    `mode` VARCHAR(16) NOT NULL DEFAULT 'sandbox' COMMENT 'sandbox/live',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='支付网关配置';

CREATE TABLE `erik_payment_gateway_methods` (
    `id` BIGINT UNSIGNED NOT NULL,
    `gateway_id` BIGINT UNSIGNED NOT NULL,
    `method_code` VARCHAR(32) NOT NULL COMMENT 'card/ideal/sofort/klarna_paylater/afterpay',
    `countries` JSON COMMENT '支持国家 ["NL","DE","BE"]',
    `currencies` JSON COMMENT '支持币种 ["EUR"]',
    `min_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.01,
    `max_amount` DECIMAL(10,2) NOT NULL DEFAULT 999999.00,
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_gateway_id` (`gateway_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='支付网关-支付方式映射';

CREATE TABLE `erik_platform_settlements` (
    `id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `payment_id` BIGINT UNSIGNED NOT NULL,
    `total_amount` DECIMAL(12,2) NOT NULL COMMENT '订单总额',
    `platform_fee` DECIMAL(12,2) NOT NULL COMMENT '平台佣金',
    `platform_fee_rate` DECIMAL(5,2) NOT NULL COMMENT '佣金率%',
    `payment_gateway_fee` DECIMAL(12,2) NOT NULL COMMENT '支付手续费',
    `supplier_amount` DECIMAL(12,2) NOT NULL COMMENT '应付供应商',
    `affiliate_amount` DECIMAL(12,2) NOT NULL COMMENT '应付分销',
    `currency_code` CHAR(3) NOT NULL DEFAULT 'USD',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待处理/1已结算',
    `settled_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='平台分账记录';

CREATE TABLE `erik_supplier_settlements` (
    `id` BIGINT UNSIGNED NOT NULL,
    `supplier_id` BIGINT UNSIGNED NOT NULL,
    `period_start` DATE NOT NULL COMMENT '结算周期开始',
    `period_end` DATE NOT NULL COMMENT '结算周期结束',
    `total_orders` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '订单数',
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '应付总额',
    `platform_fee_deducted` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '扣减佣金',
    `net_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '实付金额',
    `currency_code` CHAR(3) NOT NULL DEFAULT 'USD',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待付/1已付',
    `paid_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_supplier_id` (`supplier_id`),
    KEY `idx_period` (`period_start`, `period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='供应商结算';

CREATE TABLE `erik_currency_exchange_gains_losses` (
    `id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `received_amount` DECIMAL(12,2) NOT NULL COMMENT '收款金额',
    `received_currency` CHAR(3) NOT NULL,
    `settled_amount` DECIMAL(12,2) NOT NULL COMMENT '结算金额',
    `settled_currency` CHAR(3) NOT NULL,
    `exchange_rate_at_payment` DECIMAL(10,6) NOT NULL COMMENT '支付时汇率',
    `exchange_rate_at_settlement` DECIMAL(10,6) NOT NULL COMMENT '结算时汇率',
    `gain_loss_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '正=收益/负=亏损',
    `settled_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='汇率损益追踪';

-- =============================================================
-- 模块7: 营销 (9张)
-- =============================================================

CREATE TABLE `erik_coupons` (
    `id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(128) NOT NULL COMMENT '优惠券名称',
    `type` TINYINT UNSIGNED NOT NULL COMMENT '1满减/2折扣/3固定金额',
    `value` DECIMAL(10,2) NOT NULL COMMENT '优惠值(金额或折扣率)',
    `min_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '最低消费金额',
    `total_qty` INT UNSIGNED NOT NULL COMMENT '总发行量',
    `received_qty` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已领取',
    `used_qty` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已使用',
    `per_user_limit` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '每人限领',
    `scope_type` VARCHAR(16) NOT NULL DEFAULT 'all' COMMENT 'all/category/product',
    `scope_ids` JSON COMMENT '适用范围(all=null)',
    `countries` JSON COMMENT '限定区域 null=全部',
    `new_user_only` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '仅新用户',
    `start_at` DATETIME NULL,
    `end_at` DATETIME NULL,
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='优惠券';

CREATE TABLE `erik_user_coupons` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `coupon_id` BIGINT UNSIGNED NOT NULL,
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0未用/1已用/2已过期',
    `used_at` DATETIME NULL,
    `used_order_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_coupon` (`user_id`, `coupon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户优惠券';

CREATE TABLE `erik_flash_sales` (
    `id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(128) NOT NULL COMMENT '秒杀名称',
    `start_at` DATETIME NOT NULL COMMENT '开始时间',
    `end_at` DATETIME NOT NULL COMMENT '结束时间',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待开始/1进行中/2已结束',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='秒杀活动';

CREATE TABLE `erik_flash_sale_skus` (
    `id` BIGINT UNSIGNED NOT NULL,
    `flash_sale_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL,
    `price` DECIMAL(12,2) NOT NULL COMMENT '秒杀价格',
    `stock` INT UNSIGNED NOT NULL COMMENT '秒杀库存',
    `limit_per_user` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '每人限购',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_flash_sale_id` (`flash_sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='秒杀SKU';

CREATE TABLE `erik_group_buys` (
    `id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(128) NOT NULL COMMENT '拼团名称',
    `sku_id` BIGINT UNSIGNED NOT NULL,
    `group_price` DECIMAL(12,2) NOT NULL COMMENT '拼团价格',
    `required_count` TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT '成团人数',
    `expire_hours` INT UNSIGNED NOT NULL DEFAULT 24 COMMENT '拼团有效小时',
    `start_at` DATETIME NULL,
    `end_at` DATETIME NULL,
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='拼团活动';

CREATE TABLE `erik_affiliate_links` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '推广用户',
    `code` VARCHAR(16) NOT NULL COMMENT '推广码',
    `commission_rate` DECIMAL(5,2) NOT NULL DEFAULT 5.00 COMMENT '佣金率%',
    `landing_url` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '推广落地页',
    `total_clicks` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '点击量',
    `total_orders` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '订单量',
    `total_commission` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计佣金',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分销链接';

CREATE TABLE `erik_affiliate_commissions` (
    `id` BIGINT UNSIGNED NOT NULL,
    `affiliate_link_id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL COMMENT '佣金金额',
    `rate` DECIMAL(5,2) NOT NULL COMMENT '佣金率',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待确认/1已确认/2已结算',
    `settled_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_affiliate_link_id` (`affiliate_link_id`),
    KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分销佣金';

CREATE TABLE `erik_affiliate_payouts` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL COMMENT '提现金额',
    `method` VARCHAR(32) NOT NULL DEFAULT 'paypal' COMMENT '提现方式',
    `account` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '提现账号(加密)',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待审/1已付/2驳回',
    `paid_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分销提现';

-- =============================================================
-- 模块8: 供应链 (7张)
-- =============================================================

CREATE TABLE `erik_suppliers` (
    `id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(128) NOT NULL COMMENT '供应商名称',
    `contact_person` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '联系人',
    `email` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '邮箱(加密)',
    `phone` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '电话(加密)',
    `country_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所在国家',
    `address` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '地址',
    `rating` TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT '评级 1-5',
    `status` VARCHAR(16) NOT NULL DEFAULT 'active' COMMENT 'active/inactive/suspended',
    `payment_terms` VARCHAR(32) NOT NULL DEFAULT 'T/T' COMMENT '付款条款 T/T/L/C/Net30',
    `lead_time_days` INT UNSIGNED NOT NULL DEFAULT 15 COMMENT '平均交货天数',
    `categories` JSON COMMENT '供应品类',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='供应商';

CREATE TABLE `erik_purchase_orders` (
    `id` BIGINT UNSIGNED NOT NULL,
    `po_no` VARCHAR(32) NOT NULL COMMENT '采购单号',
    `supplier_id` BIGINT UNSIGNED NOT NULL,
    `warehouse_id` BIGINT UNSIGNED NOT NULL COMMENT '收货仓库',
    `status` VARCHAR(16) NOT NULL DEFAULT 'draft' COMMENT 'draft/pending/approved/shipped/received/qa_passed/cancelled',
    `expected_at` DATE NULL COMMENT '预计到货',
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `currency_code` CHAR(3) NOT NULL DEFAULT 'USD',
    `remark` VARCHAR(512) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_po_no` (`po_no`),
    KEY `idx_supplier_id` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购单';

CREATE TABLE `erik_purchase_order_items` (
    `id` BIGINT UNSIGNED NOT NULL,
    `po_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL COMMENT '采购数量',
    `unit_price` DECIMAL(12,2) NOT NULL COMMENT '单价',
    `subtotal` DECIMAL(12,2) NOT NULL COMMENT '小计',
    `received_qty` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '实收数量',
    `defective_qty` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '次品数量',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_po_id` (`po_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购明细';

CREATE TABLE `erik_quality_inspections` (
    `id` BIGINT UNSIGNED NOT NULL,
    `po_id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联订单(出库质检)',
    `inspector_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '质检员',
    `type` VARCHAR(16) NOT NULL DEFAULT 'incoming' COMMENT 'incoming/outgoing',
    `status` VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT 'pending/passed/failed/partial',
    `sample_rate` DECIMAL(5,2) NOT NULL DEFAULT 100.00 COMMENT '抽检比例%',
    `passed_qty` INT UNSIGNED NOT NULL DEFAULT 0,
    `failed_qty` INT UNSIGNED NOT NULL DEFAULT 0,
    `report` JSON COMMENT '质检报告',
    `inspected_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_po_id` (`po_id`),
    KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='质检记录';

CREATE TABLE `erik_quality_inspection_items` (
    `id` BIGINT UNSIGNED NOT NULL,
    `inspection_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL,
    `check_item` VARCHAR(64) NOT NULL COMMENT '检查项 外观/功能/合规标签/HS标签/包装/重量',
    `result` VARCHAR(16) NOT NULL DEFAULT 'pass' COMMENT 'pass/fail',
    `remark` VARCHAR(256) NOT NULL DEFAULT '',
    `images` JSON COMMENT '质检图片',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_inspection_id` (`inspection_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='质检项目';

-- =============================================================
-- 模块9: 风控与合规 (6张)
-- =============================================================

CREATE TABLE `erik_risk_rules` (
    `id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(64) NOT NULL COMMENT '规则名称',
    `event` VARCHAR(32) NOT NULL COMMENT '触发事件 user_register/user_login/order_create/payment_create/refund_request',
    `field` VARCHAR(64) NOT NULL COMMENT '检测字段',
    `operator` VARCHAR(16) NOT NULL COMMENT '运算符 eq/gt/lt/contains/mismatch',
    `threshold` VARCHAR(128) NOT NULL COMMENT '阈值',
    `score` INT UNSIGNED NOT NULL DEFAULT 10 COMMENT '命中分值',
    `action` VARCHAR(16) NOT NULL DEFAULT 'log' COMMENT 'log/warn/block',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_event` (`event`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='风控规则';

CREATE TABLE `erik_risk_logs` (
    `id` BIGINT UNSIGNED NOT NULL,
    `event_type` VARCHAR(32) NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `order_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `scores` JSON COMMENT '命中规则详情',
    `total_score` INT UNSIGNED NOT NULL DEFAULT 0,
    `result` VARCHAR(16) NOT NULL DEFAULT 'pass' COMMENT 'pass/warn/review',
    `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_event_type` (`event_type`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='风控日志';

CREATE TABLE `erik_privacy_requests` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `email` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '请求人邮箱',
    `type` VARCHAR(32) NOT NULL COMMENT 'data_access/data_delete/opt_out/data_portability',
    `status` VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT 'pending/processing/completed/rejected',
    `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME NULL,
    `admin_note` VARCHAR(512) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GDPR/CCPA数据请求';

CREATE TABLE `erik_cookie_consents` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `session_id` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '匿名用户session',
    `version` VARCHAR(8) NOT NULL COMMENT 'Cookie政策版本',
    `preferences` JSON COMMENT '用户选择 {necessary:true,analytics:false,marketing:false}',
    `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cookie同意记录';

CREATE TABLE `erik_privacy_policy_versions` (
    `id` BIGINT UNSIGNED NOT NULL,
    `version` VARCHAR(8) NOT NULL COMMENT '版本号',
    `content` TEXT COMMENT '政策内容',
    `locale` VARCHAR(10) NOT NULL DEFAULT 'en' COMMENT '语言',
    `published_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_version_locale` (`version`, `locale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='隐私政策版本';

-- =============================================================
-- 模块10: 多平台与渠道 (6张)
-- =============================================================

CREATE TABLE `erik_shops` (
    `id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(128) NOT NULL COMMENT '店铺名称',
    `domain` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '域名',
    `brand` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '品牌',
    `country_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '默认国家',
    `default_currency` CHAR(3) NOT NULL DEFAULT 'USD' COMMENT '默认币种',
    `default_language` VARCHAR(10) NOT NULL DEFAULT 'en' COMMENT '默认语言',
    `logo` VARCHAR(512) NOT NULL DEFAULT '' COMMENT 'Logo',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='多店铺';

CREATE TABLE `erik_platform_accounts` (
    `id` BIGINT UNSIGNED NOT NULL,
    `shop_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `platform` VARCHAR(32) NOT NULL COMMENT 'amazon/ebay/shopee/lazada/temu',
    `account_name` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '账号名',
    `api_key` VARCHAR(256) NOT NULL DEFAULT '' COMMENT 'API密钥(加密)',
    `api_secret` VARCHAR(256) NOT NULL DEFAULT '' COMMENT 'API密钥(加密)',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_shop_id` (`shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='第三方平台账号';

CREATE TABLE `erik_platform_listings` (
    `id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `platform_account_id` BIGINT UNSIGNED NOT NULL,
    `platform_product_id` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '平台商品ID',
    `status` VARCHAR(16) NOT NULL DEFAULT 'draft' COMMENT 'draft/listed/error',
    `last_synced_at` DATETIME NULL,
    `error_message` VARCHAR(512) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='平台刊登记录';

CREATE TABLE `erik_platform_orders` (
    `id` BIGINT UNSIGNED NOT NULL,
    `platform_account_id` BIGINT UNSIGNED NOT NULL,
    `shop_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `platform_order_id` VARCHAR(64) NOT NULL COMMENT '平台订单号',
    `status` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '平台订单状态',
    `internal_status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '映射内部状态',
    `buyer_name` VARCHAR(128) NOT NULL DEFAULT '',
    `buyer_email` VARCHAR(256) NOT NULL DEFAULT '',
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `currency` CHAR(3) NOT NULL DEFAULT 'USD',
    `shipping_address` JSON COMMENT '收货地址',
    `raw_data` JSON COMMENT '平台原始数据',
    `synced_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_platform_order` (`platform`, `platform_order_id`),
    KEY `idx_shop_id` (`shop_id`),
    KEY `idx_status` (`internal_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='第三方平台订单';

CREATE TABLE `erik_platform_order_items` (
    `id` BIGINT UNSIGNED NOT NULL,
    `platform_order_id` BIGINT UNSIGNED NOT NULL,
    `platform_item_id` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '平台明细ID',
    `sku_code` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '平台SKU编码',
    `title` VARCHAR(256) NOT NULL DEFAULT '',
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_platform_order_id` (`platform_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='第三方平台订单明细';

CREATE TABLE `erik_merchants` (
    `id` BIGINT UNSIGNED NOT NULL,
    `shop_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联店铺',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '卖家用户ID',
    `store_name` VARCHAR(128) NOT NULL COMMENT '店铺名',
    `contact_person` VARCHAR(64) NOT NULL DEFAULT '',
    `email` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '邮箱(加密)',
    `phone` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '电话(加密)',
    `commission_rate` DECIMAL(5,2) NOT NULL DEFAULT 5.00 COMMENT '平台抽成比例%',
    `status` VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT 'pending/active/suspended',
    `verified_at` DATETIME NULL COMMENT '审核通过时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_shop_id` (`shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='第三方卖家(多商家)';

CREATE TABLE `erik_merchant_products` (
    `id` BIGINT UNSIGNED NOT NULL,
    `merchant_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `status` VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT 'pending/approved/rejected',
    `reject_reason` VARCHAR(256) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_merchant_id` (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='卖家商品(需审核)';

CREATE TABLE `erik_merchant_settlements` (
    `id` BIGINT UNSIGNED NOT NULL,
    `merchant_id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `order_amount` DECIMAL(12,2) NOT NULL COMMENT '订单金额',
    `commission_rate` DECIMAL(5,2) NOT NULL COMMENT '抽成率',
    `commission_amount` DECIMAL(12,2) NOT NULL COMMENT '抽成金额',
    `settlement_amount` DECIMAL(12,2) NOT NULL COMMENT '结算金额',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待结算/1已结算',
    `settled_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_merchant_id` (`merchant_id`),
    KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='卖家结算';

-- =============================================================
-- 模块11: 内容与体验 (12张)
-- =============================================================

CREATE TABLE `erik_cms_pages` (
    `id` BIGINT UNSIGNED NOT NULL,
    `slug` VARCHAR(128) NOT NULL COMMENT 'URL别名',
    `type` VARCHAR(32) NOT NULL DEFAULT 'page' COMMENT 'page/blog/landing',
    `image` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '头图',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `published_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMS页面';

CREATE TABLE `erik_cms_page_translations` (
    `id` BIGINT UNSIGNED NOT NULL,
    `page_id` BIGINT UNSIGNED NOT NULL,
    `locale` VARCHAR(10) NOT NULL,
    `title` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '页面标题',
    `content` LONGTEXT COMMENT '页面内容(HTML)',
    `meta_title` VARCHAR(256) NOT NULL DEFAULT '' COMMENT 'SEO标题',
    `meta_description` VARCHAR(512) NOT NULL DEFAULT '' COMMENT 'SEO描述',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_page_locale` (`page_id`, `locale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMS页面多语言';

CREATE TABLE `erik_product_feeds` (
    `id` BIGINT UNSIGNED NOT NULL,
    `type` VARCHAR(16) NOT NULL COMMENT 'google/meta',
    `name` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'Feed名称',
    `config` JSON COMMENT 'Feed配置',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `last_synced_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品Feed配置';

CREATE TABLE `erik_product_feed_logs` (
    `id` BIGINT UNSIGNED NOT NULL,
    `feed_id` BIGINT UNSIGNED NOT NULL,
    `status` VARCHAR(16) NOT NULL DEFAULT 'success' COMMENT 'success/error',
    `product_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '同步商品数',
    `error_message` TEXT COMMENT '错误信息',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_feed_id` (`feed_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Feed同步日志';

CREATE TABLE `erik_size_charts` (
    `id` BIGINT UNSIGNED NOT NULL,
    `category_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联品类',
    `type` VARCHAR(32) NOT NULL COMMENT 'clothing/shoes/ring/belt',
    `name` VARCHAR(64) NOT NULL COMMENT '尺码表名称',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='尺码对照表';

CREATE TABLE `erik_size_chart_values` (
    `id` BIGINT UNSIGNED NOT NULL,
    `chart_id` BIGINT UNSIGNED NOT NULL,
    `region` VARCHAR(8) NOT NULL COMMENT '地区 US/UK/EU/JP/CN',
    `size_label` VARCHAR(16) NOT NULL COMMENT '尺码标签 S/M/L 或 38/39/40 或 7/8/9',
    `measurement_cm` DECIMAL(6,2) NOT NULL COMMENT '对应厘米值',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_chart_id` (`chart_id`),
    KEY `idx_region_size` (`region`, `size_label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='尺码转换值';

CREATE TABLE `erik_notifications` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=全部用户',
    `title` VARCHAR(256) NOT NULL COMMENT '通知标题',
    `content` TEXT COMMENT '通知内容',
    `type` VARCHAR(32) NOT NULL DEFAULT 'system' COMMENT 'system/order/promotion/price',
    `target_type` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '跳转类型 order/product/page',
    `target_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '跳转ID',
    `is_read` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `read_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_read` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统通知';

CREATE TABLE `erik_email_templates` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(64) NOT NULL COMMENT '模板代码 order_confirmed/shipped/password_reset/welcome',
    `locale` VARCHAR(10) NOT NULL DEFAULT 'en' COMMENT '语言',
    `subject` VARCHAR(256) NOT NULL COMMENT '邮件标题',
    `body` TEXT COMMENT '邮件正文(HTML)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code_locale` (`code`, `locale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邮件模板(多语言)';

CREATE TABLE `erik_email_logs` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `template_code` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '模板代码',
    `to_email` VARCHAR(256) NOT NULL COMMENT '收件人(加密)',
    `subject` VARCHAR(256) NOT NULL COMMENT '实际标题',
    `status` VARCHAR(16) NOT NULL DEFAULT 'sent' COMMENT 'sent/failed',
    `error_message` VARCHAR(512) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邮件发送日志';

CREATE TABLE `erik_price_alerts` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL,
    `target_price` DECIMAL(12,2) NOT NULL COMMENT '目标价格',
    `current_price` DECIMAL(12,2) NOT NULL COMMENT '当前价格',
    `is_notified` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否已通知',
    `notified_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='降价提醒';

CREATE TABLE `erik_search_logs` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `keyword` VARCHAR(256) NOT NULL COMMENT '搜索词',
    `result_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '结果数量',
    `locale` VARCHAR(10) NOT NULL DEFAULT 'en' COMMENT '搜索语言',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_keyword` (`keyword`(64)),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='搜索日志';

CREATE TABLE `erik_operation_logs` (
    `id` BIGINT UNSIGNED NOT NULL,
    `admin_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作人ID',
    `module` VARCHAR(32) NOT NULL COMMENT '操作模块 product/order/user',
    `action` VARCHAR(32) NOT NULL COMMENT '操作 create/update/delete',
    `target_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作对象ID',
    `content` JSON COMMENT '操作内容快照',
    `ip` VARCHAR(45) NOT NULL DEFAULT '' COMMENT '操作IP',
    `user_agent` VARCHAR(512) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_admin_id` (`admin_id`),
    KEY `idx_module` (`module`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作日志';

-- =============================================================
-- 模块12: 订阅/积分/礼品卡/B2B (7张)
-- =============================================================

CREATE TABLE `erik_subscriptions` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL,
    `interval_days` INT UNSIGNED NOT NULL COMMENT '间隔天数 30/60/90',
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `next_billing_at` DATE NOT NULL COMMENT '下次扣款日',
    `gateway` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '扣款网关',
    `gateway_subscription_id` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '网关订阅ID',
    `status` VARCHAR(16) NOT NULL DEFAULT 'active' COMMENT 'active/paused/cancelled',
    `paused_at` DATETIME NULL,
    `cancelled_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_next_billing` (`next_billing_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订阅周期购';

CREATE TABLE `erik_subscription_orders` (
    `id` BIGINT UNSIGNED NOT NULL,
    `subscription_id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '生成的订单ID',
    `billing_cycle` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '第几期',
    `status` VARCHAR(16) NOT NULL DEFAULT 'success' COMMENT 'success/failed',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_subscription_id` (`subscription_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订阅生成的订单';

CREATE TABLE `erik_subscription_logs` (
    `id` BIGINT UNSIGNED NOT NULL,
    `subscription_id` BIGINT UNSIGNED NOT NULL,
    `action` VARCHAR(16) NOT NULL COMMENT 'activate/pause/resume/cancel/renew/fail',
    `remark` VARCHAR(256) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_subscription_id` (`subscription_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订阅日志';

CREATE TABLE `erik_point_rules` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(32) NOT NULL COMMENT '规则代码 register/order/review/referral',
    `name` VARCHAR(64) NOT NULL COMMENT '规则名称',
    `points` INT NOT NULL COMMENT '积分值(正=获取/负=消耗)',
    `limit_type` VARCHAR(16) NOT NULL DEFAULT 'unlimited' COMMENT 'unlimited/daily/monthly',
    `limit_value` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '限制次数',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='积分规则';

CREATE TABLE `erik_point_logs` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `rule_code` VARCHAR(32) NOT NULL COMMENT '规则代码',
    `points` INT NOT NULL COMMENT '变动积分',
    `balance_after` INT NOT NULL COMMENT '变动后积分',
    `reference_type` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '关联类型 order/review',
    `reference_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='积分流水';

CREATE TABLE `erik_gift_cards` (
    `id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(32) NOT NULL COMMENT '礼品卡码',
    `denomination` DECIMAL(10,2) NOT NULL COMMENT '面额',
    `balance` DECIMAL(10,2) NOT NULL COMMENT '余额',
    `currency_code` CHAR(3) NOT NULL DEFAULT 'USD',
    `sender_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '购买人',
    `receiver_email` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '接收人邮件(加密)',
    `message` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '祝福语',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0未激活/1已激活/2已用完/3已过期',
    `expire_at` DATE NULL COMMENT '过期日',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='礼品卡';

CREATE TABLE `erik_b2b_prices` (
    `id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL,
    `min_order_qty` INT UNSIGNED NOT NULL COMMENT 'MOQ起订量',
    `price` DECIMAL(12,2) NOT NULL COMMENT '批量价格',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sku_id` (`sku_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='B2B阶梯定价';

CREATE TABLE `erik_b2b_verifications` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `company_name` VARCHAR(256) NOT NULL COMMENT '公司名',
    `tax_id` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '税号/统一信用代码(加密)',
    `business_license` VARCHAR(512) NOT NULL DEFAULT '' COMMENT '营业执照',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待审/1通过/2驳回',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='B2B企业认证';

CREATE TABLE `erik_b2b_quotes` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `sku_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `quantity` INT UNSIGNED NOT NULL COMMENT '询价数量',
    `target_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '期望价格',
    `currency_code` CHAR(3) NOT NULL DEFAULT 'USD',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0待回复/1已回复/2已关闭',
    `reply_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '回复价格',
    `reply_message` VARCHAR(512) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='B2B询价';

-- =============================================================
-- 模块13: 客服与FAQ (5张)
-- =============================================================

CREATE TABLE `erik_chat_sessions` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `agent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '客服ID 0=未分配',
    `topic` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '会话主题',
    `status` VARCHAR(16) NOT NULL DEFAULT 'waiting' COMMENT 'waiting/active/closed',
    `closed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_agent_id` (`agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客服会话';

CREATE TABLE `erik_chat_messages` (
    `id` BIGINT UNSIGNED NOT NULL,
    `session_id` BIGINT UNSIGNED NOT NULL,
    `sender_type` VARCHAR(8) NOT NULL COMMENT 'user/agent/bot',
    `sender_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `content` TEXT COMMENT '消息内容',
    `content_type` VARCHAR(16) NOT NULL DEFAULT 'text' COMMENT 'text/image/file',
    `read_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='聊天消息';

CREATE TABLE `erik_knowledge_base` (
    `id` BIGINT UNSIGNED NOT NULL,
    `category` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '分类 shipping/return/payment/product',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客服知识库';

CREATE TABLE `erik_knowledge_base_translations` (
    `id` BIGINT UNSIGNED NOT NULL,
    `kb_id` BIGINT UNSIGNED NOT NULL,
    `locale` VARCHAR(10) NOT NULL,
    `title` VARCHAR(256) NOT NULL DEFAULT '',
    `content` TEXT,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_kb_locale` (`kb_id`, `locale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='知识库多语言';

CREATE TABLE `erik_faq_translations` (
    `id` BIGINT UNSIGNED NOT NULL,
    `category` VARCHAR(64) NOT NULL COMMENT '分类',
    `locale` VARCHAR(10) NOT NULL,
    `question` VARCHAR(512) NOT NULL,
    `answer` TEXT,
    `sort` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_category_locale` (`category`, `locale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='FAQ多语言';

-- =============================================================
-- 模块14: AB测试/API治理/设置 (7张)
-- =============================================================

CREATE TABLE `erik_ab_tests` (
    `id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(128) NOT NULL COMMENT '测试名称',
    `goal` VARCHAR(64) NOT NULL COMMENT '目标 conversion/revenue/click',
    `page` VARCHAR(128) NOT NULL COMMENT '测试页面 product/cart/checkout/landing',
    `traffic_ratio` DECIMAL(4,2) NOT NULL DEFAULT 50.00 COMMENT '实验组流量比例%',
    `start_at` DATETIME NULL COMMENT '开始时间',
    `end_at` DATETIME NULL COMMENT '结束时间',
    `status` VARCHAR(16) NOT NULL DEFAULT 'draft' COMMENT 'draft/running/stopped/completed',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AB测试';

CREATE TABLE `erik_ab_test_variants` (
    `id` BIGINT UNSIGNED NOT NULL,
    `ab_test_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(64) NOT NULL COMMENT '变体名 control/experiment_a',
    `is_control` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否对照组',
    `config` JSON COMMENT '变体配置(页面元素的JSON)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ab_test_id` (`ab_test_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AB测试变体';

CREATE TABLE `erik_ab_test_results` (
    `id` BIGINT UNSIGNED NOT NULL,
    `ab_test_id` BIGINT UNSIGNED NOT NULL,
    `variant_id` BIGINT UNSIGNED NOT NULL,
    `metric` VARCHAR(32) NOT NULL COMMENT '指标 impressions/clicks/conversions/revenue',
    `value` DECIMAL(12,4) NOT NULL DEFAULT 0.0000 COMMENT '指标值',
    `sample_size` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '样本量',
    `confidence` DECIMAL(6,4) NOT NULL DEFAULT 0.0000 COMMENT '置信度',
    `recorded_at` DATE NOT NULL COMMENT '记录日期',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ab_test_variant_date` (`ab_test_id`, `variant_id`, `recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AB测试结果';

CREATE TABLE `erik_api_rate_limits` (
    `id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=IP限流',
    `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
    `endpoint` VARCHAR(128) NOT NULL COMMENT 'API端点',
    `window` VARCHAR(16) NOT NULL DEFAULT '1h' COMMENT '窗口 1m/5m/1h/24h',
    `max_hits` INT UNSIGNED NOT NULL DEFAULT 60 COMMENT '窗口内最大请求数',
    `current_hits` INT UNSIGNED NOT NULL DEFAULT 0,
    `reset_at` DATETIME NULL COMMENT '重置时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_endpoint` (`user_id`, `endpoint`),
    KEY `idx_ip_endpoint` (`ip_address`, `endpoint`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API限流记录';

CREATE TABLE `erik_api_docs` (
    `id` BIGINT UNSIGNED NOT NULL,
    `route` VARCHAR(256) NOT NULL COMMENT '路由 /api/products',
    `method` VARCHAR(8) NOT NULL COMMENT 'GET/POST/PUT/DELETE',
    `group` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '分组 商品/订单/用户',
    `title` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '接口名称',
    `description` TEXT COMMENT '接口说明',
    `parameters` JSON COMMENT '请求参数定义',
    `response_example` JSON COMMENT '响应示例',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_route_method` (`route`, `method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API文档配置';

CREATE TABLE `erik_settings` (
    `id` BIGINT UNSIGNED NOT NULL,
    `key` VARCHAR(64) NOT NULL COMMENT '配置键',
    `value` TEXT COMMENT '配置值',
    `group` VARCHAR(32) NOT NULL DEFAULT 'general' COMMENT '分组 general/shop/payment/email/risk',
    `remark` VARCHAR(256) NOT NULL DEFAULT '' COMMENT '说明',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_key` (`key`),
    KEY `idx_group` (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置(Key-Value)';

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================
-- 操作来源端平台字段 (追加)
-- =============================================================

ALTER TABLE `erik_orders` ADD COLUMN `platform` VARCHAR(16) NOT NULL DEFAULT 'web' COMMENT '操作来源端' AFTER `risk_result`;
ALTER TABLE `erik_payments` ADD COLUMN `platform` VARCHAR(16) NOT NULL DEFAULT 'web' COMMENT '支付来源端' AFTER `gateway_data`;
ALTER TABLE `erik_operation_logs` ADD COLUMN `platform` VARCHAR(16) NOT NULL DEFAULT 'web' COMMENT '操作来源端' AFTER `user_agent`;
ALTER TABLE `erik_users` ADD COLUMN `last_login_platform` VARCHAR(16) NOT NULL DEFAULT '' COMMENT '最后登录平台' AFTER `last_login_ip`;
ALTER TABLE `erik_search_logs` ADD COLUMN `platform` VARCHAR(16) NOT NULL DEFAULT 'web' COMMENT '搜索来源端' AFTER `locale`;
ALTER TABLE `erik_chat_messages` ADD COLUMN `platform` VARCHAR(16) NOT NULL DEFAULT 'web' COMMENT '消息来源端' AFTER `sender_id`;

