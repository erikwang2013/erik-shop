<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCompliance extends BaseModel
{
    use SoftDeletes;
    protected $table = "erik_product_compliance";

    public function product(): BelongsTo
    {
        return $this->belongsTo(Products::class, "product_id");
    }

    public function complianceCategory(): BelongsTo
    {
        return $this->belongsTo(ComplianceCategories::class, "compliance_category_id");
    }

}
