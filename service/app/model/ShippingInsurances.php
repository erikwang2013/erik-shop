<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingInsurances extends BaseModel
{    protected $table = "shop_shipping_insurances";
    public function order(): BelongsTo
    {
        return $this->belongsTo(Orders::class, "order_id");
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipments::class, "shipment_id");
    }

}
