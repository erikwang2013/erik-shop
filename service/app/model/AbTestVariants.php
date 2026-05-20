<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbTestVariants extends BaseModel
{    use Encryptable;
    protected $table = "erik_ab_test_variants";
    protected $encryptable = ["name"];

    public function abTest(): BelongsTo
    {
        return $this->belongsTo(AbTests::class, "ab_test_id");
    }

}
