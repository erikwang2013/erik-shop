<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\Currencies;

/**
 * @Apidoc\Group("currency")
 * @Apidoc\Sort(22)
 */
class ShopCurrencyController extends Crud
{
    protected $model = Currencies::class;
}
