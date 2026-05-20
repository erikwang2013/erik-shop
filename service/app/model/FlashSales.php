<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSales extends BaseModel
{    protected $table = "erik_flash_sales";
    public function flashSaleSkus(): HasMany
    {
        return $this->hasMany(FlashSaleSkus::class, "flash_sale_id");
    }

}
