<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentGateways extends BaseModel
{    use Encryptable;
    use SoftDeletes;
    protected $table = "erik_payment_gateways";
    protected $encryptable = ["name", "api_key", "api_secret", "webhook_secret"];

}
