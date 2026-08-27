<?php
/**
 * E2E 环境准备：为 admin 应用补齐安装向导生成的运行文件与数据。
 * 等价于手动跑一遍安装向导（数据库已由 install.sql 导入，故跳过建表）。
 * 幂等，可重复执行。
 * 用法: php scripts/e2e/provision_admin.php
 * 生成文件: admin/plugin/admin/config/{database,thinkorm}.php（向导同款内容）
 */
$root = dirname(__DIR__, 2);
$adminRoot = $root . '/admin';

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$database = getenv('DB_NAME') ?: 'erik_shop_e2e';
$user = getenv('DB_USER') ?: 'qa';
$pass = getenv('DB_PASS') ?: 'qa_pass';

$pdo = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// 1. 生成 plugin/admin/config/database.php（与 InstallController::step1 输出一致）
$dbHost = var_export($host, true);
$dbPort = var_export((int)$port, true);
$dbName = var_export($database, true);
$dbUser = var_export($user, true);
$dbPass = var_export($pass, true);
$configContent = <<<EOF
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
file_put_contents($adminRoot . '/plugin/admin/config/database.php', $configContent);
file_put_contents($adminRoot . '/plugin/admin/config/thinkorm.php', <<<EOF
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
EOF);
echo "config files written\n";

// 2. installed 标记
$time = date('Y-m-d H:i:s');
$pdo->exec("insert into `wa_options` (`name`, `value`, `created_at`, `updated_at`) values ('installed', '1', '$time', '$time') on duplicate key update `value` = '1', `updated_at` = '$time'");
echo "installed flag set\n";

// 3. 导入菜单 menu.php -> wa_rules（与 InstallController::importMenu 同逻辑）
function addMenu(array $menu, PDO $pdo): int
{
    $allow = ['title', 'key', 'icon', 'href', 'pid', 'weight', 'type'];
    $data = [];
    foreach ($allow as $c) {
        if (isset($menu[$c])) {
            $data[$c] = $menu[$c];
        }
    }
    $time = date('Y-m-d H:i:s');
    $data['created_at'] = $data['updated_at'] = $time;
    $cols = array_map(fn($c) => "`$c`", array_keys($data));
    $vals = array_map(fn($c) => ":$c", array_keys($data));
    $smt = $pdo->prepare('insert into wa_rules (' . implode(',', $cols) . ') values (' . implode(',', $vals) . ')');
    foreach ($data as $k => $v) {
        $smt->bindValue($k, $v);
    }
    $smt->execute();
    return (int)$pdo->lastInsertId();
}

function importMenu(array $menuTree, PDO $pdo)
{
    if (is_numeric(key($menuTree)) && !isset($menuTree['key'])) {
        foreach ($menuTree as $item) {
            importMenu($item, $pdo);
        }
        return;
    }
    $children = $menuTree['children'] ?? [];
    unset($menuTree['children']);
    $smt = $pdo->prepare('select * from wa_rules where `key` = :key limit 1');
    $smt->execute(['key' => $menuTree['key']]);
    $old = $smt->fetch();
    if ($old) {
        $pid = (int)$old['id'];
        $pdo->prepare('update wa_rules set title = :title, icon = :icon where `key` = :key')
            ->execute(['title' => $menuTree['title'], 'icon' => $menuTree['icon'] ?? '', 'key' => $menuTree['key']]);
    } else {
        $pid = addMenu($menuTree, $pdo);
    }
    foreach ($children as $menu) {
        $menu['pid'] = $pid;
        importMenu($menu, $pdo);
    }
}

$menus = include $adminRoot . '/plugin/admin/config/menu.php';
importMenu($menus, $pdo);
echo 'menus imported: ' . (int)$pdo->query('select count(*) from wa_rules')->fetchColumn() . " rows\n";

// 4. 测试管理员 admin / E2ePass123!
$username = 'admin';
$count = (int)$pdo->query("select count(*) from wa_admins where username = '$username'")->fetchColumn();
if ($count === 0) {
    $hash = password_hash('E2ePass123!', PASSWORD_DEFAULT);
    $smt = $pdo->prepare('insert into wa_admins (username, nickname, password, status, created_at, updated_at) values (?, ?, ?, 0, ?, ?)');
    $smt->execute([$username, 'E2E测试管理员', $hash, $time, $time]);
    $adminId = (int)$pdo->lastInsertId();
    $pdo->prepare('insert into wa_admin_roles (role_id, admin_id) values (1, ?)')->execute([$adminId]);
    echo "admin created (id=$adminId)\n";
} else {
    echo "admin already exists\n";
}
echo "provision done\n";
