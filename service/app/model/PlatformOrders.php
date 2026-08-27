<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformOrders extends BaseModel
{    use Encryptable;
    protected $table = "erik_platform_orders";
    // 仅买家 PII 加密；platform_account_id 为 BIGINT 外键（加密后写入 INT 列报 1366，且 where 查询依赖明文匹配）
    protected $encryptable = ["buyer_name", "buyer_email"];
    protected $casts = [
        'raw_data' => 'array',          // 平台原始数据 JSON 列
        'shipping_address' => 'array',  // 收货地址 JSON 列
    ];

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccounts::class, "platform_account_id");
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shops::class, "shop_id");
    }

    public function platformOrder(): BelongsTo
    {
        return $this->belongsTo(PlatformOrders::class, "platform_order_id");
    }

    public function platformOrderItems(): HasMany
    {
        return $this->hasMany(PlatformOrderItems::class, "platform_order_id");
    }

}
