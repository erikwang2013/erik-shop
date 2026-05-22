<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\MerchantSettlements;

/**
 * @Apidoc\Group("merchant")
 * @Apidoc\Sort(34)
 */
class ShopMerchantSettlementController extends Crud
{
    protected $model = MerchantSettlements::class;
}
