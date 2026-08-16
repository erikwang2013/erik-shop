<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\model\UserKyc;
use Webman\Http\Request;

/**
 * KYC 实名认证（用户侧提交 + 状态查询）
 *
 * 背景：OrderController 下单时对 kyc_required 国家校验 UserKyc.status=1，
 * 但此前无任何用户侧提交接口，实名闭环缺失（docs/PLAN-RESEARCH.md §5 差距）。
 * real_name/id_number 由 UserKyc 模型 Encryptable 加密存储。
 * admin 侧通过 ShopUserKyc 控制器审核（status 0→1/2）。
 */
class KycController
{
    /**
     * 提交/更新实名资料
     * POST /api/kyc  {real_name, id_number, id_type?}
     */
    public function submit(Request $request): \support\Response
    {
        $userId = $request->userId;
        $realName = trim((string) $request->input('real_name', ''));
        $idNumber = trim((string) $request->input('id_number', ''));
        $idType = (string) $request->input('id_type', 'id_card');

        if ($realName === '' || $idNumber === '') {
            return ApiResponse::fail('姓名和证件号不能为空', 422);
        }
        if (mb_strlen($realName) > 50 || mb_strlen($idNumber) > 64) {
            return ApiResponse::fail('姓名或证件号格式不正确', 422);
        }

        $kyc = UserKyc::where('user_id', $userId)->first();
        if ($kyc && (int) $kyc->status === 1) {
            return ApiResponse::fail('已通过实名认证，无需重复提交', 422);
        }

        if ($kyc) {
            // 驳回后重新提交：重置为待审
            $kyc->real_name = $realName;
            $kyc->id_number = $idNumber;
            $kyc->id_type = $idType;
            $kyc->status = 0;
            $kyc->reject_reason = '';
            $kyc->verified_at = null;
            $kyc->save();
        } else {
            UserKyc::create([
                'user_id' => $userId,
                'real_name' => $realName,
                'id_number' => $idNumber,
                'id_type' => $idType,
                'status' => 0,
            ]);
        }

        return ApiResponse::success(null, '提交成功，等待审核');
    }

    /**
     * 查询实名状态
     * GET /api/kyc/status
     */
    public function status(Request $request): \support\Response
    {
        $userId = $request->userId;
        $kyc = UserKyc::where('user_id', $userId)->first();

        if (!$kyc) {
            return ApiResponse::success(['submitted' => false, 'status' => 0, 'verified_at' => null, 'reject_reason' => '']);
        }
        return ApiResponse::success([
            'submitted' => true,
            'status' => (int) $kyc->status,      // 0待审/1通过/2驳回
            'id_type' => $kyc->id_type,
            'verified_at' => $kyc->verified_at,
            'reject_reason' => $kyc->reject_reason,
        ]);
    }
}
