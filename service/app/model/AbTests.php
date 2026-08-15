<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbTests extends BaseModel
{    use Encryptable;
    protected $table = "erik_ab_tests";
    protected $encryptable = [];

    public function abTestVariants(): HasMany
    {
        return $this->hasMany(AbTestVariants::class, "ab_test_id");
    }

    public function abTestResults(): HasMany
    {
        return $this->hasMany(AbTestResults::class, "ab_test_id");
    }

}
