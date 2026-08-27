<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TariffRules extends BaseModel
{    protected $table = "shop_tariff_rules";
    public function hsCode(): BelongsTo
    {
        return $this->belongsTo(HsCodes::class, "hs_code_id");
    }

}
