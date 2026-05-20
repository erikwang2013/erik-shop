<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;

class Countries extends BaseModel
{    use Encryptable;
    protected $table = "erik_countries";
    protected $encryptable = ["name_en", "name_cn", "phone_code"];

}
