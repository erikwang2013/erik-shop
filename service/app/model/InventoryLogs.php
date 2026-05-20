<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLogs extends BaseModel
{    protected $table = "erik_inventory_logs";
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouses::class, "warehouse_id");
    }

}
