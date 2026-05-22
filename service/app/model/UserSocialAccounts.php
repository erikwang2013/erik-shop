<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSocialAccounts extends BaseModel
{
    protected $table = "erik_user_social_accounts";

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, "user_id");
    }
}
