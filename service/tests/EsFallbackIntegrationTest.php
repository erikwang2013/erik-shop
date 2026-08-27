<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use app\controller\v1\SearchController;
use app\model\Products;
use app\model\SearchLogs;
use support\Db;

/**
 * 搜索 ES 降级集成测试 — 未配置 ELASTICSEARCH_HOST/ES_HOST 时 SearchController 走 MySQL LIKE 搜索
 *
 * 环境自适应：若本进程 config 已以 ES 配置加载（如 .env 设了 ES_HOST 且先于本类 boot），
 * ES 分支在测试驱动 null 下返回空结果，此时仅断言响应结构 + 搜索日志，不断言命中商品。
 */
class EsFallbackIntegrationTest extends IntegrationTestCase
{
    /** @var array<string, int[]> */
    private array $created = [];

    protected function setUp(): void
    {
        // 尽力保证本进程以"未配置 ES"状态加载 config（先于其它集成测试 boot 时生效）
        putenv('ES_HOST=');
        putenv('ELASTICSEARCH_HOST=');
        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (self::$dbAvailable) {
            foreach ($this->created as $table => $ids) {
                if ($ids) {
                    Db::table($table)->whereIn('id', $ids)->delete();
                }
            }
        }
        parent::tearDown();
    }

    private function track(string $table, int $id): void
    {
        $this->created[$table][] = $id;
    }

    /** ES 未配置（hosts 为空）时本进程可复现 SQL 降级路径，返回 true */
    private function sqlFallbackActive(): bool
    {
        return empty(config('plugin.erikwang2013.webman-scout.app.elasticsearch.hosts', []));
    }

    private function seedProduct(string $title, int $status, int $categoryId = 0): int
    {
        $p = Products::create(['title' => $title, 'status' => $status, 'category_id' => $categoryId]);
        $this->track('shop_products', (int) $p->id);
        return (int) $p->id;
    }

    private function search(string $query): array
    {
        $req = $this->makeRequest('GET', '/api/search?' . $query);
        $res = (new SearchController())->index($req);
        return json_decode($res->rawBody(), true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function empty_keyword_is_rejected(): void
    {
        $data = $this->search('keyword=');
        $this->assertSame(422, $data['code']);
        $this->assertStringContainsString('关键词', (string) $data['msg']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function keyword_search_returns_matching_published_products(): void
    {
        $this->requireDb();
        $kw = 'Zq' . substr(md5(uniqid()), 0, 8);
        $matchId = $this->seedProduct($kw . ' Blender 350W', 2);
        $this->seedProduct($kw . ' Draft Only', 1);              // 草稿：status=2 过滤排除
        $this->seedProduct('QA Unrelated ' . uniqid(), 2);       // 关键词不命中

        $data = $this->search('keyword=' . urlencode($kw));
        $this->assertSame(0, $data['code'], $data['msg'] ?? '');
        $this->assertSame(1, (int) $data['data']['page']);
        $this->assertSame(20, (int) $data['data']['per_page']);
        $this->assertIsArray($data['data']['list']);
        $this->assertArrayHasKey('total', $data['data']);
        $this->assertArrayHasKey('page', $data['data']);
        $this->assertArrayHasKey('per_page', $data['data']);

        if ($this->sqlFallbackActive()) {
            $this->assertSame(1, (int) $data['data']['total'], 'SQL 降级应命中 1 条已上架商品');
            $this->assertSame($matchId, (int) $data['data']['list'][0]['id']);
        }

        $log = SearchLogs::where('keyword', $kw)->first();
        $this->assertNotNull($log, '应记录搜索日志');
        $this->track('shop_search_logs', (int) $log->id);
        $this->assertSame((int) $data['data']['total'], (int) $log->result_count);
        $this->assertSame('en', $log->locale);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function keyword_search_filters_by_category(): void
    {
        $this->requireDb();
        $kw = 'Qa' . substr(md5(uniqid()), 0, 8);
        $inId = $this->seedProduct($kw . ' Kitchen A', 2, 111);
        $this->seedProduct($kw . ' Kitchen B', 2, 222);

        $data = $this->search('keyword=' . urlencode($kw) . '&category_id=111');
        $this->assertSame(0, $data['code'], $data['msg'] ?? '');
        $this->assertIsArray($data['data']['list']);
        $this->assertArrayHasKey('total', $data['data']);

        if ($this->sqlFallbackActive()) {
            $this->assertSame(1, (int) $data['data']['total'], '分类过滤应只命中 category_id=111 的商品');
            $this->assertSame($inId, (int) $data['data']['list'][0]['id']);
        }
    }
}
