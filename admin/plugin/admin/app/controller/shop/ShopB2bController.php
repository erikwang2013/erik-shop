<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\B2bVerifications;

/**
 * @Apidoc\Group("growth")
 * @Apidoc\Sort(48)
 */
class ShopB2bController extends Crud
{
    protected $model = B2bVerifications::class;
}
