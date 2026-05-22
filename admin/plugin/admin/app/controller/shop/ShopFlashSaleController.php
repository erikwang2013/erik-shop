<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\FlashSales;

/**
 * @Apidoc\Group("flashsale")
 * @Apidoc\Sort(14)
 */
class ShopFlashSaleController extends Crud
{
    protected $model = FlashSales::class;
}
