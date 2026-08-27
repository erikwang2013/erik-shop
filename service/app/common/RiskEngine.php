<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

use app\model\RiskLogs;
use support\Redis;

/**
 * 风控规则引擎（旁路打分）
 *
 * 依据 config/risk.php：mode=bypass 旁路模式不阻塞主流程，高分订单标记人工审核。
 * 事件：user_register / user_login / order_create / payment_create / refund_request
 *
 * 检测项（可解释、可实测）：
 *   - email_domain    临时邮箱域名黑名单
 *   - velocity_check  Redis 频率（1 小时下单数 / 同 IP 注册数）
 *   - amount_anomaly  大额订单
 *   - address_mismatch 地址国家 ≠ GeoIP 国家
 *   - ip_reputation   IP 曾触发暴力破解防护
 *   - card_bin_check  需卡 BIN 数据源，暂不启用（返回 0）
 *
 * 结果映射：score ≥ high_threshold(80) → review（人工审核）
 *          ≥ medium_threshold(50) → warn
 *          ≥ low_threshold(20)   → record
 *          其余 pass
 */
class RiskEngine
{
    /** 临时邮箱域名（高频滥用域名，可扩展） */
    private const TEMP_DOMAINS = [
        'mailinator.com', 'guerrillamail.com', 'sharklasers.com', 'yopmail.com',
        'tempmail.com', '10minutemail.com', 'throwawaymail.com', 'maildrop.cc',
        'getnada.com', 'trashmail.com',
    ];

    /**
     * 对事件打分（不抛异常，任何异常按 pass 处理）
     *
     * @param string $event 事件类型
     * @param array  $context ['user_id','ip','email','amount','order_id','address_country_iso','ip_country']
     * @return array ['score'=>int, 'result'=>'pass|record|warn|review', 'details'=>array]
     */
    public static function score(string $event, array $context = []): array
    {
        $cfg = config('risk', []);
        $checks = $cfg['checks'] ?? [];
        $score = 0;
        $details = [];

        $userId = (int) ($context['user_id'] ?? 0);
        $ip = (string) ($context['ip'] ?? '');
        $email = (string) ($context['email'] ?? '');
        $amount = (float) ($context['amount'] ?? 0);

        try {
            // 1. 临时邮箱域名
            if (!empty($email) && ($checks['email_domain'] ?? true)) {
                $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
                if ($domain !== '' && in_array($domain, self::TEMP_DOMAINS, true)) {
                    $score += 40;
                    $details['email_domain'] = "临时邮箱域名: {$domain}";
                }
            }

            // 2. 频率检查（Redis 计数）
            if ($checks['velocity_check'] ?? true) {
                $vel = $cfg['velocity'] ?? [];
                if ($event === 'order_create' && $userId > 0) {
                    $key = "shop:risk:orders:{$userId}:h:" . date('YmdH');
                    $cnt = (int) Redis::incr($key);
                    Redis::expire($key, 3600);
                    if ($cnt > (int) ($vel['order_per_hour'] ?? 10)) {
                        $score += 30;
                        $details['velocity'] = "1 小时下单 {$cnt} 次";
                    }
                }
                if ($event === 'user_register' && $ip !== '') {
                    $key = "shop:risk:reg:{$ip}:h:" . date('YmdH');
                    $cnt = (int) Redis::incr($key);
                    Redis::expire($key, 3600);
                    if ($cnt > (int) ($vel['register_per_ip_hour'] ?? 3)) {
                        $score += 30;
                        $details['velocity'] = "该 IP 1 小时注册 {$cnt} 次";
                    }
                }
            }

            // 3. 金额异常
            if (($event === 'order_create' || $event === 'payment_create') && ($checks['amount_anomaly'] ?? true)) {
                if ($amount >= 20000) {
                    $score += 40;
                    $details['amount'] = "大额订单 {$amount}";
                } elseif ($amount >= 5000) {
                    $score += 20;
                    $details['amount'] = "较大金额 {$amount}";
                }
            }

            // 4. 地址与 IP 国家不一致
            if ($event === 'order_create' && ($checks['address_mismatch'] ?? true)) {
                $addrIso = strtoupper((string) ($context['address_country_iso'] ?? ''));
                $ipIso = strtoupper((string) ($context['ip_country'] ?? ''));
                if ($addrIso !== '' && $ipIso !== '' && $addrIso !== $ipIso) {
                    $score += 20;
                    $details['address_mismatch'] = "地址国家 {$addrIso} ≠ IP 国家 {$ipIso}";
                }
            }

            // 5. IP 信誉：曾触发登录暴力破解防护
            if (!empty($ip) && ($checks['ip_reputation'] ?? true)) {
                if (Redis::exists("shop:brute:{$ip}:login")) {
                    $score += 20;
                    $details['ip_reputation'] = "IP 曾触发登录风控";
                }
            }
        } catch (\Throwable $e) {
            // 风控失败不阻塞主流程（旁路模式）
            \support\Log::warning('RiskEngine score error: ' . $e->getMessage());
        }

        $score = min(100, $score);
        $high = (int) ($cfg['high_threshold'] ?? 80);
        $medium = (int) ($cfg['medium_threshold'] ?? 50);
        $low = (int) ($cfg['low_threshold'] ?? 20);
        $result = $score >= $high ? 'review' : ($score >= $medium ? 'warn' : ($score >= $low ? 'record' : 'pass'));

        return ['score' => $score, 'result' => $result, 'details' => $details];
    }

    /**
     * 记录风控日志
     */
    public static function log(string $event, array $context, array $result): void
    {
        try {
            RiskLogs::create([
                'event_type' => $event,
                'user_id' => (int) ($context['user_id'] ?? 0),
                'order_id' => (int) ($context['order_id'] ?? 0),
                'scores' => json_encode($result['details'] ?? []),
                'total_score' => (int) ($result['score'] ?? 0),
                'result' => (string) ($result['result'] ?? 'pass'),
                'ip_address' => (string) ($context['ip'] ?? ''),
            ]);
        } catch (\Throwable $e) {
            \support\Log::warning('RiskEngine log error: ' . $e->getMessage());
        }
    }
}
