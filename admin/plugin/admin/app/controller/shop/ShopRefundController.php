<?php
namespace plugin\admin\app\controller\shop;
use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\Refunds;
use support\Request;

class ShopRefundController extends Crud
{
    protected $model = Refunds::class;

    protected function updateInput($data)
    {
        if (($data["status"] ?? 0) == 1 && empty($data["refunded_at"] ?? "")) {
            $data["refunded_at"] = date("Y-m-d H:i:s");
        }
        return $data;
    }
}
