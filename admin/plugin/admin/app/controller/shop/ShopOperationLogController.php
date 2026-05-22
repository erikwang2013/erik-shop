<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\OperationLogs;

/**
 * @Apidoc\Group("general")
 * @Apidoc\Sort(9)
 */
class ShopOperationLogController extends Crud
{
    protected $model = OperationLogs::class;
}
