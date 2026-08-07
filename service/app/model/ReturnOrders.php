<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReturnOrders extends BaseModel
{
    use SoftDeletes;
    protected $table = "erik_return_orders";
    public function order(): BelongsTo
    {
        return $this->belongsTo(Orders::class, "order_id");
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, "user_id");
    }

}
