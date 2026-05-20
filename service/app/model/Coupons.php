<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupons extends BaseModel
{    protected $table = "erik_coupons";
    public function userCoupons(): HasMany
    {
        return $this->hasMany(UserCoupons::class, "coupon_id");
    }

}
