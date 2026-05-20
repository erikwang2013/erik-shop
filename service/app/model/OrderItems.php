<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItems extends BaseModel
{    protected $table = "erik_order_items";
    public function order(): BelongsTo
    {
        return $this->belongsTo(Orders::class, "order_id");
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Products::class, "product_id");
    }

}
