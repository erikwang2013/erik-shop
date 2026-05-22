<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\PriceAlerts;

/**
 * @Apidoc\Group("growth")
 * @Apidoc\Sort(52)
 */
class ShopPriceAlertController extends Crud
{
    protected $model = PriceAlerts::class;
}
