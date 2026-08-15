<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warehouses extends BaseModel
{    use Encryptable;
    use SoftDeletes;
    protected $table = "erik_warehouses";
    protected $encryptable = [];

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipments::class, "warehouse_id");
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLogs::class, "warehouse_id");
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrders::class, "warehouse_id");
    }

}
