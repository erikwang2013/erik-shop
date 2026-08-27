<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAccounts extends BaseModel
{    use Encryptable;
    use SoftDeletes;
    protected $table = "shop_platform_accounts";
    protected $encryptable = ["account_name", "api_key", "api_secret"];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shops::class, "shop_id");
    }

    public function platformListings(): HasMany
    {
        return $this->hasMany(PlatformListings::class, "platform_account_id");
    }

    public function platformOrders(): HasMany
    {
        return $this->hasMany(PlatformOrders::class, "platform_account_id");
    }

}
