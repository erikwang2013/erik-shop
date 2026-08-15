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
    protected $encryptable = []; // 国家为公开目录数据，无需加密；原加密 name_en/name_cn/phone_code 导致 varchar(8) 存不下密文且破坏查询

}
