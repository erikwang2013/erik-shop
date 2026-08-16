-- 已存在数据库的索引补丁：与 install.sql 中 6 处新增索引保持一致
-- 仅对已建库执行（install.sql 新建库已含这些索引，无需重复执行）
-- 用法: mysql -u<user> -p <db_name> < scripts/index-fixes.sql

-- erik_refunds: 新增列 user_id 索引，用户退款列表/聚合查询避免全表扫描
ALTER TABLE `erik_refunds` ADD INDEX idx_user_id (user_id);

-- erik_return_orders: 新增列 user_id 索引，用户退货列表查询避免全表扫描
ALTER TABLE `erik_return_orders` ADD INDEX idx_user_id (user_id);

-- erik_platform_listings: 新增复合索引，按 平台账号+商品 查询刊登记录
ALTER TABLE `erik_platform_listings` ADD INDEX idx_account_product (platform_account_id, product_id);

-- erik_group_buys: 新增复合索引，按 状态+活动时间窗口 筛选拼团
ALTER TABLE `erik_group_buys` ADD INDEX idx_status_time (status, start_at, end_at);

-- erik_flash_sales: 新增复合索引，按 状态+活动时间窗口 筛选秒杀
ALTER TABLE `erik_flash_sales` ADD INDEX idx_status_time (status, start_at, end_at);

-- erik_coupons: 新增复合索引，按 状态+有效期 筛选可用优惠券
ALTER TABLE `erik_coupons` ADD INDEX idx_status_time (status, start_at, end_at);
