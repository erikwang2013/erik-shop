<?php
namespace plugin\admin\app\controller\shop;
use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\Orders;
use support\Request;
use support\exception\BusinessException;

/**
 * @Apidoc\Group("general")
 * @Apidoc\Sort(10)
 */
class ShopOrderController extends Crud
{
    protected $model = Orders::class;

    protected function afterQuery($items)
    {
        $items->load(["user", "items"]);
        foreach ($items as $item) {
            $item->status_text = self::statusText($item->status);
        }
        return $items;
    }

    protected function insertInput($data)
    {
        throw new BusinessException("不允许直接创建订单");
    }

    protected function updateInput($data)
    {
        throw new BusinessException("不允许直接修改订单，请通过退款/退货流程处理");
    }

    private static function statusText(int $s): string
    {
        return ["待付款","已付款","已发货","已收货","已完成","已取消","退款中","已退款","待审核"][$s] ?? "未知";
    }
}
