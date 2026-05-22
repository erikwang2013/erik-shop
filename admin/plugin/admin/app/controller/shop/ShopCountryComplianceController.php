<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\CountryComplianceRules;

/**
 * @Apidoc\Group("customs")
 * @Apidoc\Sort(64)
 */
class ShopCountryComplianceController extends Crud
{
    protected $model = CountryComplianceRules::class;
}
