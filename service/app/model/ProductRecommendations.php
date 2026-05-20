<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRecommendations extends BaseModel
{    protected $table = "erik_product_recommendations";
    public function product(): BelongsTo
    {
        return $this->belongsTo(Products::class, "product_id");
    }

}
