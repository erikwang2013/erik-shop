<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewayMethods extends BaseModel
{    protected $table = "shop_payment_gateway_methods";

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateways::class, 'gateway_id');
    }
}
