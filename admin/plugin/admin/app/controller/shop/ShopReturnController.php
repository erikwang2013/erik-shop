<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\ReturnOrders;

class ShopReturnController extends Crud
{
    protected $model = ReturnOrders::class;
    protected function afterQuery($items) { $items->load(["order"]); }
    protected function afterQuery($items) {
        $items->load(["order"]);
    }

}
