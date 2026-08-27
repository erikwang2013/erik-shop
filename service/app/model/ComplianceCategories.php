<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComplianceCategories extends BaseModel
{    use Encryptable;
    use SoftDeletes;
    protected $table = "shop_compliance_categories";
    protected $encryptable = [];

}
