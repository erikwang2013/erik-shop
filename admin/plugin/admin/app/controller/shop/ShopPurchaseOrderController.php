<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\PurchaseOrders;

/**
 * @Apidoc\Group("supply")
 * @Apidoc\Sort(28)
 */
class ShopPurchaseOrderController extends Crud
{
    protected $model = PurchaseOrders::class;
}
