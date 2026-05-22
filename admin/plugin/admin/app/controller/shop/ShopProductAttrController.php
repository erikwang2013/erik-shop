<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\ProductAttrs;

/**
 * @Apidoc\Group("product")
 * @Apidoc\Sort(54)
 */
class ShopProductAttrController extends Crud
{
    protected $model = ProductAttrs::class;
}
