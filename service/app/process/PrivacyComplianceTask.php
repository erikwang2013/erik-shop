<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\process;

use app\model\Orders;
use app\model\PrivacyRequests;
use app\model\UserAddresses;
use app\model\Users;
use support\Log;
use Workerman\Worker;

/**
 * 隐私合规执行任务（GDPR/CCPA）
 *
 * 背景：PrivacyController 仅登记 privacy_requests（pending），无实际执行逻辑
 * （docs/PLAN-RESEARCH.md §5 差距：GDPR/CCPA 仅有请求登记无执行）。
 *
 * 每小时处理：
 *   1. data_delete：宽限期（config/privacy.data_retention.deleted_user_grace，默认30天）
 *      过后匿名化用户（清空 email/email_hash/mobile、昵称置"已注销用户"、status=0），
 *      税务审计字段（订单/支付记录）按 retain_on_deletion 保留
 *   2. data_access / data_portability：生成用户数据导出 JSON 到 runtime/privacy-exports/
 *   3. opt_out：标记完成（营销屏蔽）
 */
class PrivacyComplianceTask
{
    private static int $interval = 3600;   // 每小时

    public function onWorkerStart(Worker $worker): void
    {
        while (true) {
            try {
                self::run();
            } catch (\Throwable $e) {
                Log::error('PrivacyComplianceTask 执行异常: ' . $e->getMessage());
            }
            sleep(self::$interval);
        }
    }

    public static function run(): void
    {
        $processed = 0;

        // 1. 数据删除宽限期处理
        $graceDays = (int) config('privacy.data_retention.deleted_user_grace', 30);
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$graceDays} days"));
        $deletes = PrivacyRequests::where('type', 'data_delete')
            ->where('status', 'pending')
            ->where('requested_at', '<', $cutoff)
            ->get();
        foreach ($deletes as $req) {
            if ((int) $req->user_id > 0) {
                Users::where('id', $req->user_id)->update([
                    'email' => '',
                    'email_hash' => '',
                    'mobile' => '',
                    'nickname' => '已注销用户',
                    'status' => 0,
                ]);
            }
            $req->status = 'completed';
            $req->completed_at = date('Y-m-d H:i:s');
            $req->admin_note = '宽限期后数据已匿名化（税务审计字段保留）';
            $req->save();
            $processed++;
        }

        // 2. 数据访问/导出
        $exports = PrivacyRequests::whereIn('type', ['data_access', 'data_portability'])
            ->where('status', 'pending')
            ->get();
        foreach ($exports as $req) {
            $dir = runtime_path() . '/privacy-exports';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $file = $dir . '/user-' . $req->user_id . '-' . date('YmdHis') . '.json';
            @file_put_contents($file, json_encode(self::collectUserData((int) $req->user_id), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $req->status = 'completed';
            $req->completed_at = date('Y-m-d H:i:s');
            $req->admin_note = '数据导出文件: ' . basename($file);
            $req->save();
            $processed++;
        }

        // 3. opt-out 营销屏蔽标记
        $opts = PrivacyRequests::where('type', 'opt_out')->where('status', 'pending')->get();
        foreach ($opts as $req) {
            $req->status = 'completed';
            $req->completed_at = date('Y-m-d H:i:s');
            $req->admin_note = '已屏蔽营销推送';
            $req->save();
            $processed++;
        }

        if ($processed > 0) {
            Log::info("PrivacyComplianceTask 完成，处理 {$processed} 条隐私请求");
        }
    }

    /**
     * 汇总用户数据（不含密码/令牌等敏感凭证）
     */
    private static function collectUserData(int $userId): array
    {
        $user = Users::where('id', $userId)->first();
        return [
            'user' => $user ? $user->only(['id', 'nickname', 'email', 'status', 'created_at']) : null,
            'addresses' => UserAddresses::where('user_id', $userId)->get()->toArray(),
            'orders' => Orders::where('user_id', $userId)
                ->get(['order_no', 'total_amount', 'currency_code', 'status', 'created_at'])
                ->toArray(),
        ];
    }
}
