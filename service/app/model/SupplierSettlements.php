<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierSettlements extends BaseModel
{    protected $table = "erik_supplier_settlements";
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Suppliers::class, "supplier_id");
    }

}
