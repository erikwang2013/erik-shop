<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\MembershipLevels;

/**
 * @Apidoc\Group("growth")
 * @Apidoc\Sort(49)
 */
class ShopMembershipController extends Crud
{
    protected $model = MembershipLevels::class;
}
