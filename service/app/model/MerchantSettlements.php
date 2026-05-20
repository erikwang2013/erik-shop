<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantSettlements extends BaseModel
{    protected $table = "erik_merchant_settlements";
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchants::class, "merchant_id");
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Orders::class, "order_id");
    }

}
