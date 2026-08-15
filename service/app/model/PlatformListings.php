<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformListings extends BaseModel
{    use Encryptable;
    protected $table = "erik_platform_listings";
    protected $encryptable = [];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Products::class, "product_id");
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccounts::class, "platform_account_id");
    }

}
