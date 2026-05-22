<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\AbTests;

/**
 * @Apidoc\Group("growth")
 * @Apidoc\Sort(66)
 */
class ShopAbTestController extends Crud
{
    protected $model = AbTests::class;
}
