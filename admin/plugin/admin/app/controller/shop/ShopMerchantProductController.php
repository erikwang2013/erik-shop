<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\MerchantProducts;

/**
 * @Apidoc\Group("merchant")
 * @Apidoc\Sort(33)
 */
class ShopMerchantProductController extends Crud
{
    protected $model = MerchantProducts::class;
}
