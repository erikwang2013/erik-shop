<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\process;

use app\model\OrderItems;
use app\model\ProductRecommendations;
use support\Log;
use Workerman\Worker;

/**
 * 推荐计算 — 每日基于近90天订单商品共现计算 item-based 协同过滤推荐
 * 结果为每商品 Top 10 相似品，写入 erik_product_recommendations(type=collaborative)
 */
class RecommendationCron
{
    private static int $interval = 86400;

    public function onWorkerStart(Worker $worker): void
    {
        while (true) {
            $start = microtime(true);
            try {
                self::run();
            } catch (\Throwable $e) {
                Log::error('RecommendationCron 执行异常: ' . $e->getMessage());
            }
            $sleep = max(1, self::$interval - (int)(microtime(true) - $start));
            sleep($sleep);
        }
    }

    public static function run(): void
    {
        $days = max(7, (int) config('cron.recommendation_days', 90));
        $deadline = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $items = OrderItems::select('order_id', 'product_id')
            ->where('created_at', '>=', $deadline)
            ->get()
            ->toArray();
        if (empty($items)) {
            return;
        }

        // 按订单聚合商品，统计两两共现次数
        $orderProducts = [];
        foreach ($items as $item) {
            $orderProducts[$item['order_id']][$item['product_id']] = true;
        }
        $cooccurrence = [];
        foreach ($orderProducts as $productIds) {
            $ids = array_keys($productIds);
            foreach ($ids as $i => $a) {
                foreach ($ids as $j => $b) {
                    if ($a !== $b) {
                        $cooccurrence[$a][$b] = ($cooccurrence[$a][$b] ?? 0) + 1;
                    }
                }
            }
        }
        if (empty($cooccurrence)) {
            return;
        }

        // 每商品取共现次数最高的 Top 10
        $recommendations = [];
        foreach ($cooccurrence as $productId => $related) {
            arsort($related);
            $top = array_slice($related, 0, 10, true);
            $maxCount = max(1, (int) reset($top));
            foreach ($top as $relatedId => $count) {
                $recommendations[] = [
                    'product_id' => $productId,
                    'recommended_product_id' => $relatedId,
                    'type' => 'collaborative',
                    'score' => round($count / $maxCount, 4),
                ];
            }
        }

        // 全量重建，避免旧推荐残留
        ProductRecommendations::where('type', 'collaborative')->delete();
        foreach (array_chunk($recommendations, 500) as $chunk) {
            ProductRecommendations::insert($chunk);
        }
        Log::info('RecommendationCron 完成，生成 ' . count($recommendations) . ' 条协同过滤推荐');
    }
}
