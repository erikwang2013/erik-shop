<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\ProductReviews;

/**
 * @Apidoc\Group("review")
 * @Apidoc\Sort(4)
 */
class ShopReviewController extends Crud
{
    protected $model = ProductReviews::class;
}
