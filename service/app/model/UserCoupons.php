<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCoupons extends BaseModel
{    protected $table = "erik_user_coupons";
    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, "user_id");
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupons::class, "coupon_id");
    }

}
