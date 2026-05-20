<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
namespace plugin\admin\app\controller\shop;
use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\Coupons;
use support\Request;
use support\exception\BusinessException;

class ShopCouponController extends Crud
{
    protected $model = Coupons::class;

    protected function insertInput(Request $request): array
    {
        $data = $request->post();
        if (strtotime($data["end_at"] ?? "") <= strtotime($data["start_at"] ?? "")) {
            throw new BusinessException("结束时间必须晚于开始时间");
        }
        return $data;
    }

    protected function updateInput(Request $request): array
    {
        $data = $request->post();
        if (strtotime($data["end_at"] ?? "") <= strtotime($data["start_at"] ?? "")) {
            throw new BusinessException("结束时间必须晚于开始时间");
        }
        return $data;
    }
}
