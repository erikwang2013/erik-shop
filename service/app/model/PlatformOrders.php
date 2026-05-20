<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformOrders extends BaseModel
{    use Encryptable;
    protected $table = "erik_platform_orders";
    protected $encryptable = ["platform_account_id", "buyer_name", "buyer_email"];

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccounts::class, "platform_account_id");
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shops::class, "shop_id");
    }

    public function platformOrder(): BelongsTo
    {
        return $this->belongsTo(PlatformOrders::class, "platform_order_id");
    }

    public function platformOrderItems(): HasMany
    {
        return $this->hasMany(PlatformOrderItems::class, "platform_order_id");
    }

}
