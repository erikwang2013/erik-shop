-- 报表中心菜单注册（幂等：先查重再插入，可重复执行）
-- 父规则（type=1 页面菜单，key=控制器类名）→ 侧边栏菜单项，href 指向报表页面
-- 子规则（type=2 方法级权限，key=类名::index）→ 与 RuleController::syncRules 的方法规则同构
-- 执行方式：mysql -u<user> -p <database> < admin/scripts/report_menu.sql

INSERT INTO `wa_rules` (`title`, `icon`, `key`, `pid`, `type`, `href`, `weight`, `created_at`, `updated_at`)
SELECT '报表中心', 'layui-icon-chart-screen', 'plugin\admin\app\controller\shop\ShopReportController', 0, 1, '/app/admin/shop/ShopReport/index', 650, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `wa_rules` WHERE `key` = 'plugin\admin\app\controller\shop\ShopReportController'
);

INSERT INTO `wa_rules` (`title`, `key`, `pid`, `type`, `weight`, `created_at`, `updated_at`)
SELECT '报表中心', 'plugin\admin\app\controller\shop\ShopReportController::index', r.id, 2, 100, NOW(), NOW()
FROM `wa_rules` r
WHERE r.`key` = 'plugin\admin\app\controller\shop\ShopReportController'
  AND NOT EXISTS (
    SELECT 1 FROM `wa_rules` WHERE `key` = 'plugin\admin\app\controller\shop\ShopReportController::index'
);
