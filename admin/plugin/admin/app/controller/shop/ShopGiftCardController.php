<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\GiftCards;

/**
 * @Apidoc\Group("growth")
 * @Apidoc\Sort(51)
 */
class ShopGiftCardController extends Crud
{
    protected $model = GiftCards::class;
}
