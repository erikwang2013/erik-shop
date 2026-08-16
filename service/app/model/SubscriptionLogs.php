<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionLogs extends BaseModel
{    protected $table = "erik_subscription_logs";
    public $timestamps = false;   // 表仅 created_at（DB 默认）
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscriptions::class, "subscription_id");
    }

}
