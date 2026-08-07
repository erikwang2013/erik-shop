<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\Coupons;
use support\Request;
use support\exception\BusinessException;

/**
 * @Apidoc\Group("coupon")
 * @Apidoc\Sort(16)
 */
class ShopCouponController extends Crud
{
    protected $model = Coupons::class;

    protected function insertInput(Request $request): array
    {
        $data = $this->allowFields($request->post());
        $this->validatePeriod($data);
        return $data;
    }

    protected function updateInput(Request $request): array
    {
        $primaryKey = $this->model->getKeyName();
        $id = $request->post($primaryKey);
        $data = $this->allowFields($request->post());
        $this->validatePeriod($data);
        return [$id, $data];
    }

    private function allowFields(array $data): array
    {
        $allow = ['title', 'type', 'value', 'min_amount', 'total_qty', 'per_user_limit', 'scope_type', 'scope_ids', 'countries', 'new_user_only', 'start_at', 'end_at', 'status'];
        return array_intersect_key($data, array_flip($allow));
    }

    private function validatePeriod(array $data): void
    {
        if (!empty($data['start_at']) && !empty($data['end_at'])
            && strtotime($data['end_at']) <= strtotime($data['start_at'])) {
            throw new BusinessException("结束时间必须晚于开始时间");
        }
    }
}
