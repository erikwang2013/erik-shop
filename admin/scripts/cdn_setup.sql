-- CDN 管理模块建表（供已安装环境执行：mysql -uroot -p shop < admin/scripts/cdn_setup.sql）
-- 全新安装由根目录 install.sql 自动导入，无需执行本文件。

CREATE TABLE IF NOT EXISTS `wa_cdn_providers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `code` varchar(32) NOT NULL COMMENT '提供商标识 cloudflare/cloudfront/aliyun/tencent',
  `name` varchar(64) NOT NULL COMMENT '显示名称',
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '启用 0否/1是',
  `config` longtext NOT NULL COMMENT '配置JSON(整体加密存储：凭据+域名等)',
  `weight` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='CDN提供商配置';

CREATE TABLE IF NOT EXISTS `wa_cdn_purge_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `provider` varchar(32) NOT NULL COMMENT '提供商',
  `type` varchar(16) NOT NULL COMMENT 'purge/purge_by_tag/preload',
  `urls` text NOT NULL COMMENT 'URL清单(JSON数组)',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1成功/0失败',
  `message` text NOT NULL COMMENT '失败信息',
  `admin_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '操作人(自动触发为0)',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_provider_created` (`provider`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='CDN刷新日志';
