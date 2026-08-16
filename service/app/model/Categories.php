<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Categories extends BaseModel
{    use Encryptable;
    use SoftDeletes;
        protected $connection = 'mysql_rw';   // 读写分离：读走 read 副本（sticky 写后读主库）
protected $table = "erik_categories";
    protected $encryptable = [];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Categories::class, "parent_id");
    }

    public function children(): HasMany
    {
        return $this->hasMany(Categories::class, "parent_id");
    }

}
