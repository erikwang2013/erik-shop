<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;

class MembershipLevels extends BaseModel
{    use Encryptable;
    protected $table = "erik_membership_levels";
    protected $encryptable = ["name"];

}
