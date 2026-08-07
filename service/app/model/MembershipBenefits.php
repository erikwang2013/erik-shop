<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipBenefits extends BaseModel
{
    use SoftDeletes;
    protected $table = "erik_membership_benefits";
}
