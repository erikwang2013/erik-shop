<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;

class ProductFeeds extends BaseModel
{    use Encryptable;
    protected $table = "erik_product_feeds";
    protected $encryptable = ["name"];

}
