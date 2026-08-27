<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatSessions extends BaseModel
{    protected $table = "shop_chat_sessions";
    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, "user_id");
    }

}
