<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyExchangeGainsLosses extends BaseModel
{    protected $table = "erik_currency_exchange_gains_losses";
    public $timestamps = false;   // 表仅 created_at 列（DB 默认），无 updated_at
    public function order(): BelongsTo
    {
        return $this->belongsTo(Orders::class, "order_id");
    }

}
