<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\process;

use app\model\Currencies;
use app\model\ExchangeRates;
use GuzzleHttp\Client as HttpClient;
use support\Log;
use Workerman\Worker;

/**
 * 汇率定时更新 — 每小时从汇率源拉取并写入 erik_exchange_rates
 * 默认使用 open.er-api.com 免费源（无需密钥），可用 cron.exchange_rate_source 覆盖
 */
class ExchangeRateCron
{
    private static int $interval = 3600;

    public function onWorkerStart(Worker $worker): void
    {
        while (true) {
            $start = microtime(true);
            try {
                self::run();
            } catch (\Throwable $e) {
                Log::error('ExchangeRateCron 执行异常: ' . $e->getMessage());
            }
            $sleep = max(1, self::$interval - (int)(microtime(true) - $start));
            sleep($sleep);
        }
    }

    public static function run(): void
    {
        $sourceUrl = config('cron.exchange_rate_source', 'https://open.er-api.com/v6/latest/USD');
        $http = new HttpClient(['timeout' => 15]);
        $response = $http->get($sourceUrl);
        $body = json_decode($response->getBody(), true) ?: [];
        if (empty($body['result']) || $body['result'] !== 'success' || empty($body['rates'])) {
            Log::error('ExchangeRateCron 汇率源返回异常: ' . substr($response->getBody(), 0, 200));
            return;
        }

        $codes = Currencies::where('status', 1)->pluck('code')->all();
        if (empty($codes)) {
            $codes = ['CNY', 'EUR', 'GBP', 'JPY', 'KRW', 'HKD'];
        }
        $rates = $body['rates'];
        $now = date('Y-m-d H:i:s');
        $updated = 0;

        foreach ($codes as $code) {
            if (!isset($rates[$code]) || $code === 'USD') {
                continue;
            }
            $rate = (float) $rates[$code];
            if ($rate <= 0) {
                continue;
            }
            foreach ([['USD', $code, $rate], [$code, 'USD', 1 / $rate]] as [$from, $to, $value]) {
                $exists = ExchangeRates::where('from_currency', $from)->where('to_currency', $to)->first();
                if ($exists) {
                    $exists->rate = $value;
                    $exists->source = 'exchangerate-api';
                    $exists->effective_at = $now;
                    $exists->save();
                } else {
                    ExchangeRates::create([
                        'from_currency' => $from,
                        'to_currency' => $to,
                        'rate' => $value,
                        'source' => 'exchangerate-api',
                        'effective_at' => $now,
                    ]);
                }
                $updated++;
            }
        }
        Log::info("ExchangeRateCron 完成，更新 {$updated} 条汇率");
    }
}
