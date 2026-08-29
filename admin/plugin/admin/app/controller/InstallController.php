<?php

namespace plugin\admin\app\controller;

use Illuminate\Database\Capsule\Manager;
use plugin\admin\app\common\Util;
use support\exception\BusinessException;
use support\Request;
use support\Response;
use Webman\Captcha\CaptchaBuilder;

/**
 * 安装向导 — 跨境电商平台一键部署
 */
class InstallController extends Base
{
    /**
     * 不需要登录的方法
     * @var string[]
     */
    protected $noNeedLogin = ['step1', 'step2'];

    /**
     * 第一步：数据库配置 + 导入完整 SQL + 生成 .env
     * @param Request $request
     * @return Response
     * @throws BusinessException|\Throwable
     */
    public function step1(Request $request): Response
    {
        $database_config_file = base_path() . '/plugin/admin/config/database.php';
        clearstatcache();
        $installed = is_file($database_config_file);
        if ($installed) {
            // 双校验：配置文件存在 + 数据库 installed 标记，仅删除文件无法重装
            $installed = $this->checkInstalledFlag($database_config_file);
        }
        if ($installed) {
            return $this->json(1, '管理后台已经安装！如需重新安装，请删除 ' . $database_config_file . ' 并清除数据库 wa_options 表中的 installed 标记，然后重启');
        }

        if (!class_exists(CaptchaBuilder::class) || !class_exists(Manager::class)) {
            return $this->json(1, '请运行 composer require -W illuminate/database 安装illuminate/database组件并重启');
        }

        $user = $request->post('user');
        $password = $request->post('password');
        $database = $request->post('database');
        $host = $request->post('host');
        $port = (int)$request->post('port') ?: 3306;
        $overwrite = $request->post('overwrite');
        $confirm = $request->post('confirm');

        try {
            $db = $this->getPdo($host, $user, $password, $port);
            $smt = $db->query('show databases like ' . $db->quote(str_replace(['%', '_'], ['\%', '\_'], $database)));
            if (empty($smt->fetchAll())) {
                $db->exec('create database `' . str_replace('`', '``', $database) . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            }
            $db->exec('use `' . str_replace('`', '``', $database) . '`');
            $smt = $db->query("show tables");
            $tables = $smt->fetchAll();
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'Access denied for user')) {
                return $this->json(1, '数据库用户名或密码错误');
            }
            if (stripos($e->getMessage(), 'Connection refused')) {
                return $this->json(1, 'Connection refused. 请确认数据库IP端口是否正确，数据库已经启动');
            }
            if (stripos($e->getMessage(), 'timed out')) {
                return $this->json(1, '数据库连接超时，请确认数据库IP端口是否正确，安全组及防火墙已经放行端口');
            }
            throw $e;
        }

        // 所有需要安装的表
        $tables_to_install = [
            'wa_admins', 'wa_admin_roles', 'wa_roles', 'wa_rules', 'wa_options', 'wa_users', 'wa_uploads',
            'wa_cdn_providers', 'wa_cdn_purge_logs',
            'shop_users', 'shop_user_addresses', 'shop_user_social_accounts', 'shop_user_kyc', 'shop_user_wishlists',
            'shop_membership_levels', 'shop_membership_benefits', 'shop_categories', 'shop_products',
            'shop_product_translations', 'shop_product_skus', 'shop_product_sku_prices', 'shop_product_images',
            'shop_product_attrs', 'shop_product_attr_values', 'shop_product_reviews', 'shop_review_translations',
            'shop_product_compliance', 'shop_compliance_categories', 'shop_product_hs_codes', 'shop_banners',
            'shop_product_comparisons', 'shop_product_recommendations', 'shop_carts', 'shop_orders',
            'shop_order_items', 'shop_order_logs', 'shop_payments', 'shop_refunds', 'shop_return_orders',
            'shop_return_labels', 'shop_order_documents', 'shop_countries', 'shop_currencies', 'shop_exchange_rates',
            'shop_logistics_companies', 'shop_shipping_zones', 'shop_shipping_zone_rates', 'shop_warehouses',
            'shop_shipments', 'shop_shipping_insurances', 'shop_inventory_logs', 'shop_inventory_transfers',
            'shop_hs_codes', 'shop_tariff_rules', 'shop_vat_settings', 'shop_country_compliance_rules',
            'shop_payment_gateways', 'shop_payment_gateway_methods', 'shop_platform_settlements',
            'shop_supplier_settlements', 'shop_currency_exchange_gains_losses', 'shop_coupons', 'shop_user_coupons',
            'shop_flash_sales', 'shop_flash_sale_skus', 'shop_group_buys', 'shop_affiliate_links',
            'shop_affiliate_commissions', 'shop_affiliate_payouts', 'shop_suppliers', 'shop_purchase_orders',
            'shop_purchase_order_items', 'shop_quality_inspections', 'shop_quality_inspection_items',
            'shop_risk_rules', 'shop_risk_logs', 'shop_privacy_requests', 'shop_cookie_consents',
            'shop_privacy_policy_versions', 'shop_shops', 'shop_platform_accounts', 'shop_platform_listings',
            'shop_platform_orders', 'shop_platform_order_items', 'shop_merchants', 'shop_merchant_products',
            'shop_merchant_settlements', 'shop_cms_pages', 'shop_cms_page_translations', 'shop_product_feeds',
            'shop_product_feed_logs', 'shop_size_charts', 'shop_size_chart_values', 'shop_notifications',
            'shop_email_templates', 'shop_email_logs', 'shop_price_alerts', 'shop_search_logs',
            'shop_operation_logs', 'shop_subscriptions', 'shop_subscription_orders', 'shop_subscription_logs',
            'shop_point_rules', 'shop_point_logs', 'shop_gift_cards', 'shop_b2b_prices', 'shop_b2b_verifications',
            'shop_b2b_quotes', 'shop_chat_sessions', 'shop_chat_messages', 'shop_knowledge_base',
            'shop_knowledge_base_translations', 'shop_faq_translations', 'shop_ab_tests', 'shop_ab_test_variants',
            'shop_ab_test_results', 'shop_api_rate_limits', 'shop_api_docs', 'shop_settings',
        ];

        $tables_exist = [];
        foreach ($tables as $table) {
            $tables_exist[] = current($table);
        }
        $tables_conflict = array_intersect($tables_to_install, $tables_exist);
        if (!$overwrite) {
            if ($tables_conflict) {
                $conflict_list = implode(',', array_slice($tables_conflict, 0, 10));
                $more = count($tables_conflict) > 10 ? '等' . count($tables_conflict) . '张表' : '';
                return $this->json(1, '以下表 ' . $conflict_list . $more . ' 已经存在，如需覆盖请勾选「强制覆盖」');
            }
        } else {
            // 二次确认：强制覆盖必须重输数据库名，防止自动化脚本误删业务数据
            if ($confirm !== $database) {
                return $this->json(1, '强制覆盖需要二次确认：请在「覆盖确认」输入框中输入数据库名 ' . $database);
            }
            foreach ($tables_conflict as $table) {
                $db->exec("DROP TABLE IF EXISTS `$table`");
            }
        }

        // 导入项目根目录 install.sql（admin/ 的上级 = shop-php 项目根）
        $sql_file = dirname(base_path(false)) . '/install.sql';
        if (!is_file($sql_file)) {
            return $this->json(1, '安装SQL文件不存在: ' . $sql_file);
        }

        $sql_content = file_get_contents($sql_file);
        $sql_content = $this->removeComments($sql_content);
        $queries = $this->splitSqlFile($sql_content, ';');
        foreach ($queries as $sql) {
            $sql = trim($sql);
            if ($sql === '') continue;
            $db->exec($sql);
        }

        // 导入业务种子数据（service/database/seeders/run.php，独立子进程避免 autoload 冲突）
        $seeder = dirname(base_path(false)) . '/service/database/seeders/run.php';
        if (is_file($seeder)) {
            $seederOutput = [];
            $seederExit = 0;
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($seeder) . ' 2>&1', $seederOutput, $seederExit);
            if ($seederExit !== 0) {
                \support\Log::warning('业务种子数据导入失败: ' . implode("\n", $seederOutput));
            }
        }

        // 导入菜单
        $menus = include base_path() . '/plugin/admin/config/menu.php';
        $this->importMenu($menus, $db);

        // 写入 plugin/admin/config/database.php（var_export 防密码含引号/美元符号破坏 PHP 文件）
        $dbHost = var_export($host, true);
        $dbPort = var_export($port, true);
        $dbName = var_export($database, true);
        $dbUser = var_export($user, true);
        $dbPass = var_export($password, true);
        $config_content = <<<EOF
<?php
return  [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver'      => 'mysql',
            'host'        => $dbHost,
            'port'        => $dbPort,
            'database'    => $dbName,
            'username'    => $dbUser,
            'password'    => $dbPass,
            'charset'     => 'utf8mb4',
            'collation'   => 'utf8mb4_general_ci',
            'prefix'      => '',
            'strict'      => true,
            'engine'      => null,
        ],
    ],
];
EOF;
        file_put_contents($database_config_file, $config_content);

        // 写入 plugin/admin/config/thinkorm.php
        $think_orm_config = <<<EOF
<?php
return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'type' => 'mysql',
            'hostname' => $dbHost,
            'database' => $dbName,
            'username' => $dbUser,
            'password' => $dbPass,
            'hostport' => $dbPort,
            'params' => [
                \\PDO::ATTR_TIMEOUT => 3,
            ],
            'charset' => 'utf8mb4',
            'prefix' => '',
            'break_reconnect' => true,
            'trigger_sql' => true,
            'bootstrap' =>  ''
        ],
    ],
];
EOF;
        file_put_contents(base_path() . '/plugin/admin/config/thinkorm.php', $think_orm_config);

        // 生成 service/.env
        $this->generateEnvFiles($host, $port, $database, $user, $password);

        // 写入 installed 标记（重装需删除配置文件并清除该标记）
        $time = date('Y-m-d H:i:s');
        $db->exec("insert into `wa_options` (`name`, `value`, `created_at`, `updated_at`) values ('installed', '1', '$time', '$time') on duplicate key update `value` = '1', `updated_at` = '$time'");

        // 尝试reload
        if (function_exists('posix_kill')) {
            set_error_handler(function () {});
            posix_kill(posix_getppid(), SIGUSR1);
            restore_error_handler();
        }

        return $this->json(0);
    }

    /**
     * 第二步：创建管理员账号
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function step2(Request $request): Response
    {
        $username = $request->post('username');
        $password = $request->post('password');
        $password_confirm = $request->post('password_confirm');
        if ($password != $password_confirm) {
            return $this->json(1, '两次密码不一致');
        }
        if (!is_file($config_file = base_path() . '/plugin/admin/config/database.php')) {
            return $this->json(1, '请先完成第一步数据库配置');
        }
        $config = include $config_file;
        $connection = $config['connections']['mysql'];
        $pdo = $this->getPdo($connection['host'], $connection['username'], $connection['password'], $connection['port'], $connection['database']);

        if ($pdo->query('select * from `wa_admins`')->fetchAll()) {
            return $this->json(1, '后台已经安装完毕，无法通过此页面创建管理员');
        }

        $smt = $pdo->prepare("insert into `wa_admins` (`username`, `password`, `nickname`, `created_at`, `updated_at`) values (:username, :password, :nickname, :created_at, :updated_at)");
        $time = date('Y-m-d H:i:s');
        $data = [
            'username' => $username,
            'password' => Util::passwordHash($password),
            'nickname' => '超级管理员',
            'created_at' => $time,
            'updated_at' => $time
        ];
        foreach ($data as $key => $value) {
            $smt->bindValue($key, $value);
        }
        $smt->execute();
        $admin_id = $pdo->lastInsertId();

        $smt = $pdo->prepare("insert into `wa_admin_roles` (`role_id`, `admin_id`) values (:role_id, :admin_id)");
        $smt->bindValue('role_id', 1);
        $smt->bindValue('admin_id', $admin_id);
        $smt->execute();

        $request->session()->flush();
        return $this->json(0);
    }

    /**
     * 校验数据库 installed 标记（配置文件存在 + DB 标记同时满足才算已安装）
     * @param string $config_file
     * @return bool
     */
    protected function checkInstalledFlag(string $config_file): bool
    {
        try {
            $config = include $config_file;
            $connection = $config['connections']['mysql'];
            $pdo = $this->getPdo($connection['host'], $connection['username'], $connection['password'], $connection['port'], $connection['database']);
            $smt = $pdo->query("select `value` from `wa_options` where `name` = 'installed' limit 1");
            $row = $smt->fetch();
            return (bool)$row && $row['value'] === '1';
        } catch (\Throwable $e) {
            // DB 不可达时按已安装处理（fail closed），不放开重装
            return true;
        }
    }

    /**
     * 生成 service/.env 和 admin/.env
     */
    protected function generateEnvFiles(string $host, string $port, string $database, string $user, string $password): void
    {
        $jwtSecret = bin2hex(random_bytes(32));
        $hashidsSalt = bin2hex(random_bytes(8));
        $encryptionKey = bin2hex(random_bytes(16));
        $adminApiKey = bin2hex(random_bytes(32));

        $sid = gethostname();
        $workerId = abs(crc32($sid . '_worker')) % 32;
        $datacenterId = abs(crc32($sid . '_dc')) % 32;

        // 生成 service/.env
        $template = file_get_contents(base_path(false) . '/service/.env.example');
        $replacements = [
            'DB_HOST=127.0.0.1' => 'DB_HOST=' . $host,
            'DB_PORT=3306' => 'DB_PORT=' . $port,
            'DB_NAME=shop' => 'DB_NAME=' . $database,
            'DB_USER=erik' => 'DB_USER=' . $user,
            'DB_PASS=change_me' => 'DB_PASS=' . $password,
            'JWT_SECRET=change_me_to_random_256bit_string' => 'JWT_SECRET=' . $jwtSecret,
            'JWT_SECRET_KEY=' => 'JWT_SECRET_KEY=' . $jwtSecret,
            'HASHIDS_SALT=change_me_hashids_salt' => 'HASHIDS_SALT=' . $hashidsSalt,
            'ENCRYPTION_KEY=change_me_32_byte_key_here!!' => 'ENCRYPTION_KEY=' . $encryptionKey,
            'SNOWFLAKE_WORKER_ID=1' => 'SNOWFLAKE_WORKER_ID=' . $workerId,
            'SNOWFLAKE_DATACENTER_ID=1' => 'SNOWFLAKE_DATACENTER_ID=' . $datacenterId,
            'ADMIN_API_KEY=change_me_admin_api_key_64bit' => 'ADMIN_API_KEY=' . $adminApiKey,
        ];
        $envContent = str_replace(array_keys($replacements), array_values($replacements), $template);
        file_put_contents(base_path(false) . '/service/.env', $envContent);

        // 生成 admin/.env（admin 的 config/database.php 也从 getenv 读取）
        $adminEnv = <<<EOF
# Admin — 数据库连接（与 service 共享同一数据库）
DB_HOST={$host}
DB_PORT={$port}
DB_NAME={$database}
DB_USER={$user}
DB_PASS={$password}
HASHIDS_SALT={$hashidsSalt}
SNOWFLAKE_WORKER_ID={$workerId}
SNOWFLAKE_DATACENTER_ID={$datacenterId}
# service 内部接口（执行真实退款等管理操作，与 service/.env 的 ADMIN_API_KEY 同值）
ADMIN_SERVICE_URL=http://127.0.0.1:8787
ADMIN_API_KEY={$adminApiKey}
EOF;
        file_put_contents(base_path() . '/.env', $adminEnv);
    }

    /**
     * 添加菜单
     */
    protected function addMenu(array $menu, \PDO $pdo): int
    {
        $allow_columns = ['title', 'key', 'icon', 'href', 'pid', 'weight', 'type'];
        $data = [];
        foreach ($allow_columns as $column) {
            if (isset($menu[$column])) {
                $data[$column] = $menu[$column];
            }
        }
        $time = date('Y-m-d H:i:s');
        $data['created_at'] = $data['updated_at'] = $time;
        $values = [];
        foreach ($data as $k => $v) {
            $values[] = ":$k";
        }
        $columns = array_keys($data);
        foreach ($columns as $k => $column) {
            $columns[$k] = "`$column`";
        }
        $sql = "insert into wa_rules (" .implode(',', $columns). ") values (" . implode(',', $values) . ")";
        $smt = $pdo->prepare($sql);
        foreach ($data as $key => $value) {
            $smt->bindValue($key, $value);
        }
        $smt->execute();
        return $pdo->lastInsertId();
    }

    /**
     * 导入菜单
     */
    protected function importMenu(array $menu_tree, \PDO $pdo)
    {
        if (is_numeric(key($menu_tree)) && !isset($menu_tree['key'])) {
            foreach ($menu_tree as $item) {
                $this->importMenu($item, $pdo);
            }
            return;
        }
        $children = $menu_tree['children'] ?? [];
        unset($menu_tree['children']);
        $smt = $pdo->prepare("select * from wa_rules where `key`=:key limit 1");
        $smt->execute(['key' => $menu_tree['key']]);
        $old_menu = $smt->fetch();
        if ($old_menu) {
            $pid = $old_menu['id'];
            $params = [
                'title' => $menu_tree['title'],
                'icon' => $menu_tree['icon'] ?? '',
                'key' => $menu_tree['key'],
            ];
            $sql = "update wa_rules set title=:title, icon=:icon where `key`=:key";
            $smt = $pdo->prepare($sql);
            $smt->execute($params);
        } else {
            $pid = $this->addMenu($menu_tree, $pdo);
        }
        foreach ($children as $menu) {
            $menu['pid'] = $pid;
            $this->importMenu($menu, $pdo);
        }
    }

    /**
     * 去除sql文件中的注释
     */
    protected function removeComments($sql): string
    {
        return preg_replace("/(\n--[^\n]*)/","", $sql);
    }

    /**
     * 分割sql文件
     */
    function splitSqlFile($sql, $delimiter): array
    {
        $tokens = explode($delimiter, $sql);
        $output = array();
        $matches = array();
        $token_count = count($tokens);
        for ($i = 0; $i < $token_count; $i++) {
            if (($i != ($token_count - 1)) || (strlen($tokens[$i] > 0))) {
                $total_quotes = preg_match_all("/'/", $tokens[$i], $matches);
                $escaped_quotes = preg_match_all("/(?<!\\\\)(\\\\\\\\)*\\\\'/", $tokens[$i], $matches);
                $unescaped_quotes = $total_quotes - $escaped_quotes;

                if (($unescaped_quotes % 2) == 0) {
                    $output[] = $tokens[$i];
                    $tokens[$i] = "";
                } else {
                    $temp = $tokens[$i] . $delimiter;
                    $tokens[$i] = "";

                    $complete_stmt = false;
                    for ($j = $i + 1; (!$complete_stmt && ($j < $token_count)); $j++) {
                        $total_quotes = preg_match_all("/'/", $tokens[$j], $matches);
                        $escaped_quotes = preg_match_all("/(?<!\\\\)(\\\\\\\\)*\\\\'/", $tokens[$j], $matches);
                        $unescaped_quotes = $total_quotes - $escaped_quotes;
                        if (($unescaped_quotes % 2) == 1) {
                            $output[] = $temp . $tokens[$j];
                            $tokens[$j] = "";
                            $temp = "";
                            $complete_stmt = true;
                            $i = $j;
                        } else {
                            $temp .= $tokens[$j] . $delimiter;
                            $tokens[$j] = "";
                        }
                    }
                }
            }
        }

        return $output;
    }

    /**
     * 获取pdo连接
     */
    protected function getPdo($host, $username, $password, $port, $database = null): \PDO
    {
        $dsn = "mysql:host=$host;port=$port;";
        if ($database) {
            $dsn .= "dbname=$database";
        }
        $params = [
            \PDO::MYSQL_ATTR_INIT_COMMAND => "set names utf8mb4",
            \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_TIMEOUT => 5,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ];
        return new \PDO($dsn, $username, $password, $params);
    }

}
