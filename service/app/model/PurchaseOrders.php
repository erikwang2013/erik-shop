<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrders extends BaseModel
{
    use SoftDeletes;
    protected $table = "shop_purchase_orders";
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Suppliers::class, "supplier_id");
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouses::class, "warehouse_id");
    }

}
