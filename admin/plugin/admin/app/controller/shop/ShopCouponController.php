<?php
namespace plugin\admin\app\controller\shop;
use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\Coupons;
use support\Request;
use supportxception\BusinessException;

class ShopCouponController extends Crud
{
    protected $model = Coupons::class;

    protected function insertInput($data)
    {
        if (strtotime($data["end_at"]) <= strtotime($data["start_at"])) {
            throw new BusinessException("结束时间必须晚于开始时间");
        }
        return $data;
    }

    protected function updateInput($data)
    {
        if (strtotime($data["end_at"] ?? "") <= strtotime($data["start_at"] ?? "")) {
            throw new BusinessException("结束时间必须晚于开始时间");
        }
        return $data;
    }
}
