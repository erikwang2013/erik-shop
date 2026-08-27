<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLogs extends BaseModel
{    use Encryptable;
    protected $table = "erik_email_logs";
    public $timestamps = false;   // 表仅 created_at 列（DB 默认），无 updated_at
    protected $encryptable = ["to_email"];

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, "user_id");
    }

}
