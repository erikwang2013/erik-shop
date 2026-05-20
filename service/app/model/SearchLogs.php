<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchLogs extends BaseModel
{    protected $table = "erik_search_logs";
    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, "user_id");
    }

}
