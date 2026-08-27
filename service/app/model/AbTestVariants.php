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
    public $timestamps = false;   // 表仅 created_at 列（DB 默认），无 updated_at
    protected $encryptable = [];

    public function abTest(): BelongsTo
    {
        return $this->belongsTo(AbTests::class, "ab_test_id");
    }

}
