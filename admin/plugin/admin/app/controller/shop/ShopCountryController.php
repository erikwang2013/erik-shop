<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\Countries;

/**
 * @Apidoc\Group("general")
 * @Apidoc\Sort(3)
 */
class ShopCountryController extends Crud
{
    protected $model = Countries::class;
}
