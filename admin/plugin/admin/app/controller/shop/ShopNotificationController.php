<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\Notifications;

/**
 * @Apidoc\Group("notification")
 * @Apidoc\Sort(21)
 */
class ShopNotificationController extends Crud
{
    protected $model = Notifications::class;
}
