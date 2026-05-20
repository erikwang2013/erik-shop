<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HsCodes extends BaseModel
{    protected $table = "erik_hs_codes";
    public function productHsCodes(): HasMany
    {
        return $this->hasMany(ProductHsCodes::class, "hs_code_id");
    }

    public function tariffRules(): HasMany
    {
        return $this->hasMany(TariffRules::class, "hs_code_id");
    }

}
