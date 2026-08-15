<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Suppliers extends BaseModel
{    use Encryptable;
    use SoftDeletes;
    protected $table = "erik_suppliers";
    protected $encryptable = ["email", "phone"];

    public function supplierSettlements(): HasMany
    {
        return $this->hasMany(SupplierSettlements::class, "supplier_id");
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrders::class, "supplier_id");
    }

}
