<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipments extends BaseModel
{    protected $table = "shop_shipments";
    public function order(): BelongsTo
    {
        return $this->belongsTo(Orders::class, "order_id");
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouses::class, "warehouse_id");
    }

    public function shippingInsurances(): HasMany
    {
        return $this->hasMany(ShippingInsurances::class, "shipment_id");
    }

}
