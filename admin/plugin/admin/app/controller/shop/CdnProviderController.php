<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use app\common\Cdn;
use app\common\CdnException;
use plugin\admin\app\controller\Base;
use plugin\admin\app\model\Option;
use plugin\admin\app\model\shop\CdnProviders;
use plugin\admin\app\model\shop\CdnPurgeLogs;
use support\Request;
use support\Response;

/**
 * CDN 管理：全局设置 / 提供商配置 / 手动刷新预热 / 刷新日志
 * 自动路由 /app/admin/shop/CdnProvider/{action}
 */
class CdnProviderController extends Base
{
    /** 支持的提供商及其显示名（白名单，save/test 均校验） */
    private const PROVIDERS = [
        'cloudflare' => 'Cloudflare',
        'cloudfront' => 'AWS CloudFront',
        'aliyun'     => '阿里云 CDN',
        'tencent'    => '腾讯云 CDN',
    ];

    /** 各提供商可配置字段（凭据留空 = 不修改既有值） */
    private const FIELDS = [
        'cloudflare' => ['api_token', 'zone_id'],
        'cloudfront' => ['key_id', 'secret_key', 'distribution_id', 'region'],
        'aliyun'     => ['access_key_id', 'access_key_secret'],
        'tencent'    => ['secret_id', 'secret_key'],
    ];

    /** code 白名单（save/test/run 共用；静态便于单测） */
    public static function isSupported(string $code): bool
    {
        return isset(self::PROVIDERS[$code]);
    }

    /** 提交配置与既有配置合并：空值不覆盖既有值（save 共用；静态纯函数便于单测） */
    public static function mergeConfig(array $existing, array $input, string $code): array
    {
        foreach (self::FIELDS[$code] ?? [] as $field) {
            $value = trim((string) ($input[$field] ?? ''));
            if ($value !== '') {
                $existing[$field] = $value;
            }
        }
        return $existing;
    }

    public function index(): Response
    {
        return raw_view('shop/cdn/index');
    }

    /** 提供商列表：4 家固定 code 与 DB 行 left join（名称/启用/凭据掩码/来源） */
    public function select(Request $request): Response
    {
        $rows = CdnProviders::orderByDesc('weight')->get()->keyBy('code');
        $data = [];
        foreach (self::PROVIDERS as $code => $name) {
            $row = $rows->get($code);
            $db = $row ? (array) json_decode((string) $row->config, true) : [];
            $merged = array_merge((array) config("cdn.providers.$code", []), $db);
            $data[] = [
                'code' => $code,
                'name' => $row->name ?? $name,
                'enabled' => (int) ($row->enabled ?? 0),
                'source' => $row ? 'db' : 'env',
                'fields' => array_map(
                    static fn (string $f) => ['key' => $f, 'masked' => !empty($merged[$f])],
                    self::FIELDS[$code]
                ),
            ];
        }
        return $this->json(0, 'ok', ['count' => count($data), 'data' => $data]);
    }

    /** 保存提供商：code 白名单校验；凭据留空不覆盖既有值；config 整体加密落库 */
    public function save(Request $request): Response
    {
        $code = (string) $request->post('code', '');
        if (!self::isSupported($code)) {
            return $this->json(1, '未知提供商: ' . $code);
        }
        $row = CdnProviders::where('code', $code)->first();
        $existing = $row ? (array) json_decode((string) $row->config, true) : [];
        $config = self::mergeConfig($existing, $request->post(), $code);
        CdnProviders::updateOrCreate(['code' => $code], [
            'name' => (string) $request->post('name', self::PROVIDERS[$code]),
            'enabled' => $request->post('enabled') ? 1 : 0,
            'config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'weight' => (int) $request->post('weight', 0),
        ]);
        return $this->json(0);
    }

    /** 全局设置读写（wa_options.cdn_settings），保存后失效 Redis 缓存（service 侧 URL 重写读取） */
    public function settings(Request $request): Response
    {
        if ($request->method() === 'POST') {
            $default = (string) $request->post('default', 'cloudflare');
            if (!isset(self::PROVIDERS[$default])) {
                return $this->json(1, '默认提供商无效');
            }
            $json = json_encode([
                'enabled' => $request->post('enabled') ? 1 : 0,
                'domain' => trim((string) $request->post('domain', '')),
                'default' => $default,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            Option::updateOrCreate(['name' => 'cdn_settings'], ['value' => $json]);
            // 注：admin 无 redis 依赖，缓存主动失效缺失 → 接受 service 侧 60s TTL 传播，与缓存自洽
            return $this->json(0);
        }
        $value = Option::where('name', 'cdn_settings')->value('value');
        return $this->json(0, 'ok', $value ? ((array) json_decode((string) $value, true)) : []);
    }

    /** 连通性测试：按 code 实例化并 purge 测试文件；403/401=凭据错误，其余异常=不可达 */
    public function test(Request $request): Response
    {
        $code = (string) $request->get('code', '');
        if (!self::isSupported($code)) {
            return $this->json(1, '未知提供商');
        }
        try {
            Cdn::make($code)->purge(['/__cdn_test__.txt']);
            return $this->json(0, 'ok', ['code' => $code]);
        } catch (CdnException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'HTTP 401') || str_contains($msg, 'HTTP 403')) {
                return $this->json(1, '凭据错误：' . $msg);
            }
            return $this->json(1, '不可达：' . $msg);
        } catch (\Throwable $e) {
            return $this->json(1, '不可达：' . $e->getMessage());
        }
    }

    /** 手动刷新（外域 URL 丢弃） */
    public function purge(Request $request): Response
    {
        return $this->run('purge', $request);
    }

    /** 手动预热（不支持 preload 的提供商返回错误信息） */
    public function preload(Request $request): Response
    {
        return $this->run('preload', $request);
    }

    private function run(string $action, Request $request): Response
    {
        $code = (string) $request->post('code', '');
        if (!self::isSupported($code)) {
            return $this->json(1, '未知提供商');
        }
        $raw = $request->post('urls', []);
        $urls = is_array($raw)
            ? array_values(array_filter(array_map('trim', $raw)))
            : preg_split('/[\s,]+/', trim((string) $raw), -1, PREG_SPLIT_NO_EMPTY);
        $urls = Cdn::normalizeUrls($urls);
        if ($urls === []) {
            return $this->json(1, '无可' . ($action === 'purge' ? '刷新' : '预热') . '的有效 URL（外域已丢弃）');
        }
        $status = 1;
        $message = '';
        try {
            $provider = Cdn::make($code);
            if ($action === 'purge') {
                $provider->purge($urls);
            } else {
                $provider->preload($urls);
            }
        } catch (\Throwable $e) {
            $status = 0;
            $message = $e->getMessage();
        }
        try {
            CdnPurgeLogs::create([
                'provider' => $code,
                'type' => $action,
                'urls' => json_encode($urls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => $status,
                'message' => $message,
                'admin_id' => 0,
            ]);
        } catch (\Throwable $e) {
            // 日志写失败不影响主流程
        }
        return $status === 1 ? $this->json(0) : $this->json(1, $message);
    }

    /** 刷新日志（倒序分页） */
    public function log(Request $request): Response
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 20)));
        $query = CdnPurgeLogs::query();
        $provider = trim((string) $request->get('provider', ''));
        if ($provider !== '') {
            $query->where('provider', $provider);
        }
        $total = $query->count();
        $rows = (clone $query)->orderByDesc('id')->forPage($page, $limit)->get()->map(function ($row) {
            $row->urls = json_decode((string) $row->urls, true) ?: [];
            return $row;
        });
        return $this->json(0, 'ok', ['count' => $total, 'data' => $rows]);
    }
}
