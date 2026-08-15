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
    protected $encryptable = ["api_key", "api_secret", "webhook_secret"]; // name 为公开网关名，加密后按 code/name 查询失效

}
