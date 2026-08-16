<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchLogs extends BaseModel
{
    public $timestamps = false;   // 表仅 created_at（DB 默认 CURRENT_TIMESTAMP），无 updated_at
    protected $table = "erik_search_logs";
    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, "user_id");
    }

}
