<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\process;

use app\model\CountryComplianceRules;
use GuzzleHttp\Client as HttpClient;
use support\Log;
use Workerman\Worker;

/**
 * 合规规则更新 — 每日检查外部合规规则源（可配置 URL）
 * 配置 cron.compliance_source_url 后拉取 JSON 规则同步到 erik_country_compliance_rules；
 * 未配置时由管理后台人工维护，跳过拉取
 */
class ComplianceCron
{
    private static int $interval = 86400;

    public function onWorkerStart(Worker $worker): void
    {
        while (true) {
            $start = microtime(true);
            try {
                self::run();
            } catch (\Throwable $e) {
                Log::error('ComplianceCron 执行异常: ' . $e->getMessage());
            }
            $sleep = max(1, self::$interval - (int)(microtime(true) - $start));
            sleep($sleep);
        }
    }

    public static function run(): void
    {
        $sourceUrl = (string) config('cron.compliance_source_url', '');
        if ($sourceUrl === '') {
            Log::info('ComplianceCron 跳过：未配置 cron.compliance_source_url，合规规则由管理后台维护');
            return;
        }

        $http = new HttpClient(['timeout' => 15]);
        $response = $http->get($sourceUrl);
        $rules = json_decode($response->getBody(), true) ?: [];
        if (empty($rules)) {
            Log::error('ComplianceCron 规则源返回数据格式错误');
            return;
        }

        $updated = 0;
        foreach ($rules as $rule) {
            $countryId = (int) ($rule['country_id'] ?? 0);
            $categoryId = (int) ($rule['compliance_category_id'] ?? 0);
            $value = (string) ($rule['rule'] ?? '');
            if ($countryId <= 0 || $categoryId <= 0 || !in_array($value, ['allowed', 'restricted', 'prohibited'], true)) {
                continue;
            }
            $exists = CountryComplianceRules::where('country_id', $countryId)
                ->where('compliance_category_id', $categoryId)
                ->first();
            if ($exists) {
                $exists->rule = $value;
                $exists->restriction_reason = (string) ($rule['restriction_reason'] ?? $exists->restriction_reason);
                $exists->save();
            } else {
                CountryComplianceRules::create([
                    'country_id' => $countryId,
                    'compliance_category_id' => $categoryId,
                    'rule' => $value,
                    'restriction_reason' => (string) ($rule['restriction_reason'] ?? ''),
                ]);
            }
            $updated++;
        }
        Log::info("ComplianceCron 完成，同步 {$updated} 条合规规则");
    }
}
