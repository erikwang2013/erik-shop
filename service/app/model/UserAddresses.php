<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddresses extends BaseModel
{    use Encryptable;
    use SoftDeletes;
    protected $table = "erik_user_addresses";
    protected $encryptable = ["name", "phone", "detail"];

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, "user_id");
    }

}
