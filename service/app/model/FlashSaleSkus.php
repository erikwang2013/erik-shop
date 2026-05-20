<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSaleSkus extends BaseModel
{    protected $table = "erik_flash_sale_skus";
    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSales::class, "flash_sale_id");
    }

}
