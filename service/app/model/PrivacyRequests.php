<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivacyRequests extends BaseModel
{    use Encryptable;
    protected $table = "erik_privacy_requests";
    protected $encryptable = ["email"];

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, "user_id");
    }

}
