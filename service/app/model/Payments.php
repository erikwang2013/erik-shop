<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payments extends BaseModel
{    protected $table = "shop_payments";
    public function order(): BelongsTo
    {
        return $this->belongsTo(Orders::class, "order_id");
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, "user_id");
    }

    public function platformSettlements(): HasMany
    {
        return $this->hasMany(PlatformSettlements::class, "payment_id");
    }

}
