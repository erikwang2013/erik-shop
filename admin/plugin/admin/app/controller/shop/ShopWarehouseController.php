<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\Warehouses;

/**
 * @Apidoc\Group("warehouse")
 * @Apidoc\Sort(11)
 */
class ShopWarehouseController extends Crud
{
    protected $model = Warehouses::class;
}
