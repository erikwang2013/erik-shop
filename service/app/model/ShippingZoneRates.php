<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingZoneRates extends BaseModel
{
    use SoftDeletes;
    protected $table = "erik_shipping_zone_rates";

    public function logistics(): BelongsTo
    {
        return $this->belongsTo(LogisticsCompanies::class, 'logistics_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZones::class, 'zone_id');
    }
}
