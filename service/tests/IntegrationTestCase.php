<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use PHPUnit\Framework\TestCase;
use Webman\Http\Request;

/**
 * 业务集成测试基类
 *
 * 环境变量与 CI（.github/workflows/ci.yml）对齐：DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS
 * 未设置时默认连本地 127.0.0.1 的独立测试库 shop_test（root/root）。
 * 数据库不可用时相关用例 markTestSkipped，不阻断其余用例。
 */
abstract class IntegrationTestCase extends TestCase
{
    private static bool $booted = false;
    protected static bool $dbAvailable = false;
    /** 默认测试库（未显式指定 DB_NAME）时每个用例前清库，避免跨用例数据累积 */
    protected static bool $resetEachTest = false;

    protected static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        // 未显式指定 DB_NAME 时默认连独立测试库 shop_test（含唯一索引的种子用随机值，避免残留行冲突）
        $explicitDb = getenv('DB_NAME');
        self::$resetEachTest = !$explicitDb;
        putenv('DB_NAME=' . ($explicitDb ?: 'shop_test'));
        putenv('DB_USER=' . (getenv('DB_USER') ?: 'root'));
        putenv('DB_PASS=' . (getenv('DB_PASS') ?: 'root'));
        putenv('HASHIDS_SALT=' . (getenv('HASHIDS_SALT') ?: 'qa-integration-test-salt'));
        putenv('STRIPE_WEBHOOK_SECRET=' . (getenv('STRIPE_WEBHOOK_SECRET') ?: 'whsec_qa_integration_test'));
        // 集成测试不依赖 ES：商品模型 Searchable trait 会同步索引，改用 scout null driver 免连 ES
        putenv('OPENSEARCH_SCOUT_DRIVER=null');

        require_once dirname(__DIR__) . '/vendor/autoload.php';
        if (class_exists('Dotenv\Dotenv') && is_file(dirname(__DIR__) . '/.env')) {
            // immutable：不覆盖上面 putenv 的测试值
            \Dotenv\Dotenv::createUnsafeImmutable(dirname(__DIR__))->safeLoad();
        }
        // 排除 route：路由文件依赖容器/请求上下文，控制器直接调用不经过路由
        \Webman\Config::load(dirname(__DIR__) . '/config', ['route']);
        require_once dirname(__DIR__) . '/vendor/webman/database/src/support/Db.php';

        try {
            \support\Db::connection()->select('SELECT 1');
            self::$dbAvailable = true;
        } catch (\Throwable) {
            self::$dbAvailable = false;
            return;
        }
        if (!$explicitDb) {
            self::resetTestSchema();
        }
    }

    /**
     * 默认测试库（未显式指定 DB_NAME）每次运行前清空全部表，保证可重复执行
     * 显式指定 DB_NAME（如 CI 的 erik_shop_test）不重置，交由环境自行管理
     *
     * 性能：117 张表逐条 TRUNCATE 实测约 9.2s/次（70 用例基线整轮 9.5min），
     * 本库主键均为 Snowflake（无 AUTO_INCREMENT 业务表），DELETE FROM 语义等价且快 26 倍。
     * 仅 wa_* 管理后台表含 AUTO_INCREMENT，需 TRUNCATE 复位自增计数器。
     */
    private static function resetTestSchema(): void
    {
        $pdo = \support\Db::connection()->getPdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $tables = [];
        foreach ($pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            // MySQL 8 按 information_schema 原始列名返回 TABLE_NAME（大写），兼容两种大小写
            $tables[] = $row['TABLE_NAME'] ?? $row['table_name'];
        }
        foreach ($tables as $table) {
            if (str_starts_with($table, 'wa_')) {
                $pdo->exec('TRUNCATE TABLE `' . $table . '`');
            } else {
                $pdo->exec('DELETE FROM `' . $table . '`');
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function setUp(): void
    {
        self::boot();
        // boot() 只执行一次（static guard），默认测试库需每个用例前清库，避免跨用例数据累积
        if (self::$dbAvailable && self::$resetEachTest) {
            self::resetTestSchema();
        }
    }

    protected function requireDb(): void
    {
        if (!self::$dbAvailable) {
            $this->markTestSkipped(
                'MySQL 不可用：设置 DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS（默认 127.0.0.1:3306/shop_test）并导入 install.sql 后启用'
            );
        }
    }

    /**
     * 构造原始 HTTP 请求（JSON body），可覆盖 rawBody 供 webhook 验签场景
     */
    protected function makeRequest(string $method, string $uri, array $body = [], array $headers = [], ?string $rawBody = null): Request
    {
        $rawBody ??= json_encode($body);
        $lines = [
            "{$method} {$uri} HTTP/1.1",
            'Host: localhost',
            'Content-Type: application/json',
            'Content-Length: ' . strlen($rawBody),
        ];
        foreach ($headers as $k => $v) {
            $lines[] = "$k: $v";
        }
        return new Request(implode("\r\n", $lines) . "\r\n\r\n" . $rawBody);
    }
}
