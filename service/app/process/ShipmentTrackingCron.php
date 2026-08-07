<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\process;

use app\model\LogisticsCompanies;
use app\model\Shipments;
use GuzzleHttp\Client as HttpClient;
use support\Log;
use Workerman\Worker;

/**
 * 物流轨迹拉取 — 每30分钟查询运输中包裹的轨迹
 * 物流商在 erik_logistics_companies.api_key 配置了密钥时真实调用；
 * 轨迹 API 模板用 cron.tracking_api_url 配置（{tracking_no} 占位符），未配置则跳过
 */
class ShipmentTrackingCron
{
    private static int $interval = 1800;

    public function onWorkerStart(Worker $worker): void
    {
        while (true) {
            $start = microtime(true);
            try {
                self::run();
            } catch (\Throwable $e) {
                Log::error('ShipmentTrackingCron 执行异常: ' . $e->getMessage());
            }
            $sleep = max(1, self::$interval - (int)(microtime(true) - $start));
            sleep($sleep);
        }
    }

    public static function run(): void
    {
        $apiUrl = (string) config('cron.tracking_api_url', '');
        if ($apiUrl === '') {
            Log::info('ShipmentTrackingCron 跳过：未配置 cron.tracking_api_url 轨迹查询接口');
            return;
        }

        $shipments = Shipments::whereIn('status', [1, 2])
            ->where('tracking_no', '!=', '')
            ->orderBy('id', 'desc')
            ->limit(200)
            ->get();
        if ($shipments->isEmpty()) {
            return;
        }

        $http = new HttpClient(['timeout' => 15]);
        $processed = 0;
        foreach ($shipments as $shipment) {
            $logistics = LogisticsCompanies::find($shipment->logistics_id);
            if (!$logistics || $logistics->api_key === '') {
                continue; // 物流商未配置密钥，跳过该单
            }
            $url = str_replace('{tracking_no}', urlencode($shipment->tracking_no), $apiUrl);
            try {
                $response = $http->get($url, ['headers' => ['Authorization' => 'Bearer ' . $logistics->api_key]]);
                $body = json_decode($response->getBody(), true);
                $state = strtolower((string) ($body['status'] ?? $body['state'] ?? ''));
                $statusMap = ['delivered' => 3, 'delivery' => 3, 'exception' => 4, 'failed' => 4, 'transit' => 2, 'in_transit' => 2];
                if (isset($statusMap[$state])) {
                    $shipment->status = $statusMap[$state];
                    if ($shipment->status === 3 && !$shipment->delivered_at) {
                        $shipment->delivered_at = date('Y-m-d H:i:s');
                    }
                    $shipment->save();
                    $processed++;
                }
            } catch (\Throwable $e) {
                Log::warning('ShipmentTrackingCron 拉取轨迹失败 tracking_no=' . $shipment->tracking_no . ': ' . $e->getMessage());
            }
        }
        Log::info("ShipmentTrackingCron 完成，更新 {$processed} 单轨迹");
    }
}
