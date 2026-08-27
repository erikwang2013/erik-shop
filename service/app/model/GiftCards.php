<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;

class GiftCards extends BaseModel
{    use Encryptable;
    protected $table = "shop_gift_cards";
    protected $encryptable = ["receiver_email"];

}
