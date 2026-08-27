<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;

class ProductSkuPrices extends BaseModel
{
    use SoftDeletes;
    protected $table = "shop_product_sku_prices";
}
