<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

class SizeChartValues extends BaseModel
{    protected $table = "erik_size_chart_values";
    public $timestamps = false;   // 表仅 created_at 列（DB 默认），无 updated_at
}
