<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\model\shop;

use plugin\admin\app\model\Base;

class Orders extends Base
{
    protected $table = "erik_orders";

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItems::class, 'order_id', 'id');
    }
}
