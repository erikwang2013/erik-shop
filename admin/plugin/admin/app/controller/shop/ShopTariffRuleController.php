<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\TariffRules;

/**
 * @Apidoc\Group("customs")
 * @Apidoc\Sort(11)
 */
class ShopTariffRuleController extends Crud
{
    protected $model = TariffRules::class;
}
