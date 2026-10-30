<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupons extends BaseModel
{
    use SoftDeletes;
    protected $table = "shop_coupons";
    protected $casts = [
        'countries' => 'array',   // JSON 列：限定国家 ISO2 列表
        'scope_ids' => 'array',   // JSON 列：适用分类/商品 ID 列表
    ];
    public function userCoupons(): HasMany
    {
        return $this->hasMany(UserCoupons::class, "coupon_id");
    }

}
