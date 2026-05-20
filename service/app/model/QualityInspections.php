<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityInspections extends BaseModel
{    protected $table = "erik_quality_inspections";
    public function order(): BelongsTo
    {
        return $this->belongsTo(Orders::class, "order_id");
    }

}
