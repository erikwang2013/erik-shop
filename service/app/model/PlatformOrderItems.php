<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformOrderItems extends BaseModel
{    protected $table = "erik_platform_order_items";
    public function platformOrder(): BelongsTo
    {
        return $this->belongsTo(PlatformOrders::class, "platform_order_id");
    }

}
