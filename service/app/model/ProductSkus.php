<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductSkus extends BaseModel
{
    use SoftDeletes;
    protected $table = "shop_product_skus";

    public function product(): BelongsTo
    {
        return $this->belongsTo(Products::class, "product_id");
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductSkuPrices::class, "sku_id");
    }

}
