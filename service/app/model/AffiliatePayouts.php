<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliatePayouts extends BaseModel
{    use Encryptable;
    protected $table = "shop_affiliate_payouts";
    protected $encryptable = ["account"];

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, "user_id");
    }

}
