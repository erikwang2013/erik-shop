<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\Refunds;
use support\Request;

/**
 * @Apidoc\Group("refund")
 * @Apidoc\Sort(7)
 */
class ShopRefundController extends Crud
{
    protected $model = Refunds::class;

    protected function insertInput(Request $request): array
    {
        $allow = ['order_id', 'user_id', 'refund_no', 'type', 'amount', 'reason', 'images', 'status', 'reject_reason'];
        return array_intersect_key($request->post(), array_flip($allow));
    }

    protected function updateInput(Request $request): array
    {
        $primaryKey = $this->model->getKeyName();
        $id = $request->post($primaryKey);
        $allow = ['status', 'reject_reason', 'refunded_at'];
        $data = array_intersect_key($request->post(), array_flip($allow));
        if (($data['status'] ?? 0) == 1 && empty($data['refunded_at'] ?? '')) {
            $data['refunded_at'] = date('Y-m-d H:i:s');
        }
        return [$id, $data];
    }
}
