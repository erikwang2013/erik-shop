<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\LogisticsCompanies;

/**
 * @Apidoc\Group("logistics")
 * @Apidoc\Sort(8)
 */
class ShopLogisticsController extends Crud
{
    protected $model = LogisticsCompanies::class;
}
