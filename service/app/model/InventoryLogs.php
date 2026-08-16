<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLogs extends BaseModel
{    protected $table = "erik_inventory_logs";
    public $timestamps = false;   // 表仅 created_at 列（DB 默认），无 updated_at
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouses::class, "warehouse_id");
    }

}
