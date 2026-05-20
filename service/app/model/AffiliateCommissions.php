<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommissions extends BaseModel
{    protected $table = "erik_affiliate_commissions";
    public function affiliateLink(): BelongsTo
    {
        return $this->belongsTo(AffiliateLinks::class, "affiliate_link_id");
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Orders::class, "order_id");
    }

}
