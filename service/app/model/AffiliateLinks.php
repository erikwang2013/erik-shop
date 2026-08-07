<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AffiliateLinks extends BaseModel
{
    use SoftDeletes;
    protected $table = "erik_affiliate_links";
    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, "user_id");
    }

    public function affiliateCommissions(): HasMany
    {
        return $this->hasMany(AffiliateCommissions::class, "affiliate_link_id");
    }

}
