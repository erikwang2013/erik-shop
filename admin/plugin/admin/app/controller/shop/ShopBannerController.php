<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\Banners;

/**
 * @Apidoc\Group("banner")
 * @Apidoc\Sort(13)
 */
class ShopBannerController extends Crud
{
    protected $model = Banners::class;
}
