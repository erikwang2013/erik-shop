<?php
namespace plugin\admin\app\controller\shop;
use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\Payments;
use support\Request;

/**
 * @Apidoc\Group("payment")
 * @Apidoc\Sort(6)
 */
class ShopPaymentController extends Crud
{
    protected $model = Payments::class;

    public function insert(Request $request): \support\Response { return $this->json(1, "支付记录只读，不允许新增"); }
    public function update(Request $request): \support\Response { return $this->json(1, "支付记录只读，不允许修改"); }
    public function delete(Request $request): \support\Response { return $this->json(1, "支付记录只读，不允许删除"); }
}
