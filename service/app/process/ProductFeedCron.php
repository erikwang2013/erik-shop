<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\process;

use app\common\Money;
use app\model\ProductFeedLogs;
use app\model\ProductFeeds;
use app\model\Products;
use app\model\ProductSkus;
use support\Log;
use Workerman\Worker;

/**
 * 商品Feed同步 — 每6小时按 shop_product_feeds 配置生成 Google/Meta TSV Feed
 * 文件输出到 public/feeds/ 目录，同步结果写入 shop_product_feed_logs
 */
class ProductFeedCron
{
    private static int $interval = 3600;

    public function onWorkerStart(Worker $worker): void
    {
        while (true) {
            $start = microtime(true);
            try {
                self::run();
            } catch (\Throwable $e) {
                Log::error('ProductFeedCron 执行异常: ' . $e->getMessage());
            }
            $sleep = max(1, self::$interval - (int)(microtime(true) - $start));
            sleep($sleep);
        }
    }

    public static function run(): void
    {
        self::$interval = max(60, (int) config('feed.sync_interval', 60)) * 60;
        $feeds = ProductFeeds::where('status', 1)->get();
        if ($feeds->isEmpty()) {
            return;
        }

        $dir = (string) config('feed.output_path', base_path() . '/public/feed');
        $dir = rtrim($dir, '/');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        foreach ($feeds as $feed) {
            try {
                $products = Products::where('status', 2)->get();
                $lines = ["id\ttitle\tlink\timage_link\tprice\tavailability\tbrand"];
                foreach ($products as $product) {
                    $sku = ProductSkus::where('product_id', $product->id)->first();
                    // Feed 金额字段：协议边界十进制收敛（Money::format），不落 float
                    $price = $sku ? Money::format((string) $sku->default_price) : '0.00';
                    $lines[] = implode("\t", [
                        $product->id,
                        str_replace(["\t", "\n"], ' ', (string) $product->title),
                        config('app.app_url', 'https://erik.xyz') . '/product/' . $product->id,
                        $product->image ?? '',
                        Money::cmp($price, '0') > 0 ? $price . ' USD' : '',
                        'in_stock',
                        $product->brand ?? '',
                    ]);
                }
                $filename = "{$feed->id}_{$feed->type}_" . date('Ymd') . '.tsv';
                file_put_contents("{$dir}/{$filename}", implode("\n", $lines));

                $feed->last_synced_at = date('Y-m-d H:i:s');
                $feed->save();
                ProductFeedLogs::create([
                    'feed_id' => $feed->id,
                    'status' => 'success',
                    'product_count' => count($products),
                ]);
                Log::info("ProductFeedCron 完成 feed#{$feed->id}，生成 {$filename}（" . count($products) . ' 商品）');
            } catch (\Throwable $e) {
                ProductFeedLogs::create([
                    'feed_id' => $feed->id,
                    'status' => 'error',
                    'error_message' => substr($e->getMessage(), 0, 500),
                ]);
                Log::error("ProductFeedCron feed#{$feed->id} 生成失败: " . $e->getMessage());
            }
        }
    }
}
