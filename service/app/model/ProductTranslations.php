<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductTranslations extends BaseModel
{
    use SoftDeletes;
    protected $table = "erik_product_translations";
    public function product(): BelongsTo
    {
        return $this->belongsTo(Products::class, "product_id");
    }

}
