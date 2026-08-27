<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceAlerts extends BaseModel
{
    protected $table = "shop_price_alerts";

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, "user_id");
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSkus::class, "sku_id");
    }

}
