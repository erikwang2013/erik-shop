<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\process;

use app\model\PlatformAccounts;
use app\model\PlatformOrders;
use GuzzleHttp\Client as HttpClient;
use support\Log;
use Workerman\Worker;

/**
 * 多平台订单同步 — 每5分钟拉取外部平台（amazon/ebay/shopee等）订单
 * 需在 shop_platform_accounts 配置账号 API 密钥，且配置 cron.platform_sync_url（{account_id} 占位符）；
 * 未配置时跳过
 */
class PlatformOrderSyncCron
{
    private static int $interval = 300;

    public function onWorkerStart(Worker $worker): void
    {
        while (true) {
            $start = microtime(true);
            try {
                self::run();
            } catch (\Throwable $e) {
                Log::error('PlatformOrderSyncCron 执行异常: ' . $e->getMessage());
            }
            $sleep = max(1, self::$interval - (int)(microtime(true) - $start));
            sleep($sleep);
        }
    }

    public static function run(): void
    {
        $syncUrl = (string) config('cron.platform_sync_url', '');
        if ($syncUrl === '') {
            Log::info('PlatformOrderSyncCron 跳过：未配置 cron.platform_sync_url 平台订单接口');
            return;
        }

        $accounts = PlatformAccounts::where('status', 1)->get();
        if ($accounts->isEmpty()) {
            return;
        }

        $http = new HttpClient(['timeout' => 20]);
        $synced = 0;
        foreach ($accounts as $account) {
            if ($account->api_key === '') {
                continue; // 账号未配置 API 密钥，跳过
            }
            $url = str_replace('{account_id}', (string) $account->id, $syncUrl);
            try {
                $response = $http->get($url, ['headers' => ['Authorization' => 'Bearer ' . $account->api_key]]);
                $orders = json_decode($response->getBody(), true);
                if (!is_array($orders)) {
                    Log::warning('PlatformOrderSyncCron 账号#' . $account->id . ' 返回格式错误');
                    continue;
                }
                foreach ($orders as $order) {
                    $platformOrderId = (string) ($order['order_id'] ?? '');
                    if ($platformOrderId === '') {
                        continue;
                    }
                    $exists = PlatformOrders::where('platform_account_id', $account->id)
                        ->where('platform_order_id', $platformOrderId)
                        ->first();
                    $data = [
                        'shop_id' => $account->shop_id,
                        'platform_account_id' => $account->id,   // NOT NULL 外键，缺失则 INSERT 报 1364
                        'platform' => $account->platform,
                        'platform_order_id' => $platformOrderId,
                        'status' => (string) ($order['status'] ?? ''),
                        'buyer_name' => (string) ($order['buyer_name'] ?? ''),
                        'buyer_email' => (string) ($order['buyer_email'] ?? ''),
                        'total_amount' => (string) ($order['total_amount'] ?? 0), // decimal 列直存字符串
                        'currency' => (string) ($order['currency'] ?? 'USD'),
                        'raw_data' => $order,
                        'synced_at' => date('Y-m-d H:i:s'),
                    ];
                    if ($exists) {
                        $exists->fill($data);
                        $exists->save();
                    } else {
                        PlatformOrders::create($data);
                    }
                    $synced++;
                }
            } catch (\Throwable $e) {
                Log::warning('PlatformOrderSyncCron 同步账号#' . $account->id . ' 失败: ' . $e->getMessage());
            }
        }
        Log::info("PlatformOrderSyncCron 完成，同步 {$synced} 条平台订单");
    }
}
