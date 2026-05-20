<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;

class ShippingZones extends BaseModel
{    use Encryptable;
    protected $table = "erik_shipping_zones";
    protected $encryptable = ["name"];

}
