<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskLogs extends BaseModel
{    protected $table = "erik_risk_logs";
    public $timestamps = false;   // 表仅 created_at 列（DB 默认），无 updated_at
    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, "user_id");
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Orders::class, "order_id");
    }

}
