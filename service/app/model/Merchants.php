<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Merchants extends BaseModel
{    use Encryptable;
    use SoftDeletes;
    protected $table = "erik_merchants";
    protected $encryptable = ["email", "phone"];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shops::class, "shop_id");
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, "user_id");
    }

    public function merchantProducts(): HasMany
    {
        return $this->hasMany(MerchantProducts::class, "merchant_id");
    }

    public function merchantSettlements(): HasMany
    {
        return $this->hasMany(MerchantSettlements::class, "merchant_id");
    }

}
