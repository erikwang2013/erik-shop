<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\PlatformOrders;

/**
 * @Apidoc\Group("platform")
 * @Apidoc\Sort(37)
 */
class ShopPlatformOrderController extends Crud
{
    protected $model = PlatformOrders::class;
}
