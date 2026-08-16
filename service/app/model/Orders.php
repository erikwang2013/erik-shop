<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Orders extends BaseModel
{
    use SoftDeletes;
    protected $table = "erik_orders";
    protected $casts = [
        'address_snapshot' => 'array',   // JSON 列：数组自动序列化/反序列化
    ];
    public function productReviews(): HasMany
    {
        return $this->hasMany(ProductReviews::class, "order_id");
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, "user_id");
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItems::class, "order_id");
    }

    /**
     * items 别名（OrderController::show 等使用 with('items')）
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItems::class, "order_id");
    }

    /**
     * 订单操作日志（with('logs')）
     */
    public function logs(): HasMany
    {
        return $this->hasMany(OrderLogs::class, "order_id");
    }

    /**
     * 订单单据（发票/装箱单，with('documents')）
     */
    public function documents(): HasMany
    {
        return $this->hasMany(OrderDocuments::class, "order_id");
    }

    public function orderLogs(): HasMany
    {
        return $this->hasMany(OrderLogs::class, "order_id");
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payments::class, "order_id");
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refunds::class, "order_id");
    }

    public function returnOrders(): HasMany
    {
        return $this->hasMany(ReturnOrders::class, "order_id");
    }

    public function orderDocuments(): HasMany
    {
        return $this->hasMany(OrderDocuments::class, "order_id");
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipments::class, "order_id");
    }

    public function shippingInsurances(): HasMany
    {
        return $this->hasMany(ShippingInsurances::class, "order_id");
    }

    public function platformSettlements(): HasMany
    {
        return $this->hasMany(PlatformSettlements::class, "order_id");
    }

    public function currencyExchangeGainsLosses(): HasMany
    {
        return $this->hasMany(CurrencyExchangeGainsLosses::class, "order_id");
    }

    public function affiliateCommissions(): HasMany
    {
        return $this->hasMany(AffiliateCommissions::class, "order_id");
    }

    public function qualityInspections(): HasMany
    {
        return $this->hasMany(QualityInspections::class, "order_id");
    }

    public function riskLogs(): HasMany
    {
        return $this->hasMany(RiskLogs::class, "order_id");
    }

    public function merchantSettlements(): HasMany
    {
        return $this->hasMany(MerchantSettlements::class, "order_id");
    }

    public function subscriptionOrders(): HasMany
    {
        return $this->hasMany(SubscriptionOrders::class, "order_id");
    }

}
