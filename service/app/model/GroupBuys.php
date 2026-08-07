<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;

class GroupBuys extends BaseModel
{
    use SoftDeletes;
    protected $table = "erik_group_buys";
}
