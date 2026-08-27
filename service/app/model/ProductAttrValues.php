<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;

class ProductAttrValues extends BaseModel
{
    use SoftDeletes;
    protected $table = "shop_product_attr_values";
}
