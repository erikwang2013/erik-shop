<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\process;

use app\model\Notifications;
use app\model\PriceAlerts;
use app\common\Money;
use app\model\ProductSkuPrices;
use app\model\ProductSkus;
use support\Log;
use Workerman\Worker;

/**
 * 降价/到货通知 — 每10分钟检查未触发的价格提醒，达标则写入站内通知
 */
class PriceAlertCron
{
    private static int $interval = 600;

    public function onWorkerStart(Worker $worker): void
    {
        while (true) {
            $start = microtime(true);
            try {
                self::run();
            } catch (\Throwable $e) {
                Log::error('PriceAlertCron 执行异常: ' . $e->getMessage());
            }
            $sleep = max(1, self::$interval - (int)(microtime(true) - $start));
            sleep($sleep);
        }
    }

    public static function run(): void
    {
        $alerts = PriceAlerts::where('is_notified', 0)->orderBy('id', 'desc')->limit(500)->get();
        $notified = 0;
        foreach ($alerts as $alert) {
            $sku = ProductSkus::find($alert->sku_id);
            if (!$sku) {
                // SKU 已删除，标记失效避免僵尸告警
                $alert->is_notified = 1;
                $alert->notified_at = date('Y-m-d H:i:s');
                $alert->save();
                continue;
            }
            $currentPrice = ProductSkuPrices::where('sku_id', $alert->sku_id)
                ->where('currency_code', 'USD')
                ->value('price');
            if ($currentPrice === null) {
                $currentPrice = $sku->default_price; // decimal 列返回字符串
            }
            // 现价与目标价均为字符串，降价判定走十进制比较
            $currentPrice = Money::round((string) $currentPrice);
            $alert->current_price = $currentPrice;
            if (Money::cmp($currentPrice, (string) $alert->target_price) <= 0) {
                $alert->is_notified = 1;
                $alert->notified_at = date('Y-m-d H:i:s');
                $alert->save();
                Notifications::create([
                    'user_id' => $alert->user_id,
                    'title' => '降价提醒',
                    'content' => "您关注的商品已降至 {$currentPrice} USD",
                    'type' => 'price',
                    'target_type' => 'product',
                    'target_id' => $sku->product_id,
                ]);
                $notified++;
            } else {
                $alert->save(); // 仅刷新 current_price
            }
        }
        if ($notified > 0) {
            Log::info("PriceAlertCron 完成，触发 {$notified} 条降价提醒");
        }
    }
}
