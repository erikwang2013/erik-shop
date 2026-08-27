<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSaleSkus extends BaseModel
{    protected $table = "erik_flash_sale_skus";
    public $timestamps = false;   // 表仅 created_at 列（DB 默认），无 updated_at
    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSales::class, "flash_sale_id");
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSkus::class, "sku_id");
    }

}
