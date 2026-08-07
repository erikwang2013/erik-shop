<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserWishlists extends BaseModel
{
    use SoftDeletes;
    protected $table = "erik_user_wishlists";
    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, "user_id");
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Products::class, "product_id");
    }

}
