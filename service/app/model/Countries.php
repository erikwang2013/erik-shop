<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Countries extends BaseModel
{    use Encryptable;
    use SoftDeletes;
    protected $table = "erik_countries";
    protected $encryptable = ["name_en", "name_cn", "phone_code"];

}
