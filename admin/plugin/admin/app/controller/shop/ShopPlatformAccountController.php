<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\PlatformAccounts;

/**
 * @Apidoc\Group("platform")
 * @Apidoc\Sort(35)
 */
class ShopPlatformAccountController extends Crud
{
    protected $model = PlatformAccounts::class;
}
