<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Carts extends BaseModel
{
    use SoftDeletes;
    protected $table = "erik_carts";

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, "user_id");
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Products::class, "product_id");
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSkus::class, "sku_id");
    }

}
