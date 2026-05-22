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

    protected function updateInput(Request $request): array
    {
        $data = $request->post();
        if (($data["status"] ?? 0) == 1 && empty($data["refunded_at"] ?? "")) {
            $data["refunded_at"] = date("Y-m-d H:i:s");
        }
        return $data;
    }
}
