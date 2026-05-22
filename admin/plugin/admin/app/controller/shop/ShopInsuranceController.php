<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\ShippingInsurances;

/**
 * @Apidoc\Group("logistics")
 * @Apidoc\Sort(61)
 */
class ShopInsuranceController extends Crud
{
    protected $model = ShippingInsurances::class;
}
