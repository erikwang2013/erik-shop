<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\VatSettings;

/**
 * @Apidoc\Group("vat")
 * @Apidoc\Sort(15)
 */
class ShopVatSettingController extends Crud
{
    protected $model = VatSettings::class;
}
