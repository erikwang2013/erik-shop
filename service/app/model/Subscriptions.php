<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscriptions extends BaseModel
{    protected $table = "erik_subscriptions";
    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, "user_id");
    }

    public function subscriptionOrders(): HasMany
    {
        return $this->hasMany(SubscriptionOrders::class, "subscription_id");
    }

    public function subscriptionLogs(): HasMany
    {
        return $this->hasMany(SubscriptionLogs::class, "subscription_id");
    }

}
