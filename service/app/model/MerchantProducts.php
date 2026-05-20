<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantProducts extends BaseModel
{    protected $table = "erik_merchant_products";
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchants::class, "merchant_id");
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Products::class, "product_id");
    }

}
