<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shops extends BaseModel
{    use Encryptable;
    use SoftDeletes;
    protected $table = "erik_shops";
    protected $encryptable = ["name"];

    public function platformAccounts(): HasMany
    {
        return $this->hasMany(PlatformAccounts::class, "shop_id");
    }

    public function platformOrders(): HasMany
    {
        return $this->hasMany(PlatformOrders::class, "shop_id");
    }

    public function merchants(): HasMany
    {
        return $this->hasMany(Merchants::class, "shop_id");
    }

}
