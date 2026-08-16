<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\Users;

/**
 * @Apidoc\Group("user")
 * @Apidoc\Sort(6)
 */
class ShopUserController extends Crud
{
    protected $model = Users::class;
}
