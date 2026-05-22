<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\ProductTranslations;

/**
 * @Apidoc\Group("product")
 * @Apidoc\Sort(58)
 */
class ShopProductTranslationController extends Crud
{
    protected $model = ProductTranslations::class;
}
