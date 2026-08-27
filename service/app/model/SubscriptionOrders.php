<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionOrders extends BaseModel
{    protected $table = "shop_subscription_orders";
    public $timestamps = false;   // 表仅 created_at（DB 默认）
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscriptions::class, "subscription_id");
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Orders::class, "order_id");
    }

}
