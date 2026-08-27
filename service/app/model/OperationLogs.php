<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

class OperationLogs extends BaseModel
{    protected $table = "shop_operation_logs";
    public $timestamps = false;   // 表仅 created_at 列（DB 默认），无 updated_at
}
