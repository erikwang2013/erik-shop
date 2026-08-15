<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Users extends BaseModel
{    use Encryptable;
    use SoftDeletes;
    protected $table = "erik_users";
    protected $encryptable = ["email", "mobile"]; // password用bcrypt哈希，非加密
    protected $hidden = ["password", "salt", "deleted_at"];

    /**
     * 邮箱可搜索索引值（HMAC-SHA256，小写规范化）
     *
     * email 字段为 Encryptable 加密存储（不可直接 where 查询），
     * 登录/查重统一通过本方法计算索引列 email_hash 精确匹配。
     * key 使用 JWT_SECRET（32 字节随机值，已 fail-closed）。
     */
    public static function emailHash(string $email): string
    {
        $key = (string) config('jwt.secret_key', '');
        if ($key === '') {
            $key = (string) env('JWT_SECRET', '');
        }
        return hash_hmac('sha256', strtolower(trim($email)), $key);
    }

    public function userAddresses(): HasMany
    {
        return $this->hasMany(UserAddresses::class, "user_id");
    }

    public function userSocialAccounts(): HasMany
    {
        return $this->hasMany(UserSocialAccounts::class, "user_id");
    }

    public function userKyc(): HasMany
    {
        return $this->hasMany(UserKyc::class, "user_id");
    }

    public function userWishlists(): HasMany
    {
        return $this->hasMany(UserWishlists::class, "user_id");
    }

    public function productReviews(): HasMany
    {
        return $this->hasMany(ProductReviews::class, "user_id");
    }

    public function productComparisons(): HasMany
    {
        return $this->hasMany(ProductComparisons::class, "user_id");
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Carts::class, "user_id");
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Orders::class, "user_id");
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payments::class, "user_id");
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refunds::class, "user_id");
    }

    public function returnOrders(): HasMany
    {
        return $this->hasMany(ReturnOrders::class, "user_id");
    }

    public function userCoupons(): HasMany
    {
        return $this->hasMany(UserCoupons::class, "user_id");
    }

    public function affiliateLinks(): HasMany
    {
        return $this->hasMany(AffiliateLinks::class, "user_id");
    }

    public function affiliatePayouts(): HasMany
    {
        return $this->hasMany(AffiliatePayouts::class, "user_id");
    }

    public function riskLogs(): HasMany
    {
        return $this->hasMany(RiskLogs::class, "user_id");
    }

    public function privacyRequests(): HasMany
    {
        return $this->hasMany(PrivacyRequests::class, "user_id");
    }

    public function cookieConsents(): HasMany
    {
        return $this->hasMany(CookieConsents::class, "user_id");
    }

    public function merchants(): HasMany
    {
        return $this->hasMany(Merchants::class, "user_id");
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notifications::class, "user_id");
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLogs::class, "user_id");
    }

    public function priceAlerts(): HasMany
    {
        return $this->hasMany(PriceAlerts::class, "user_id");
    }

    public function searchLogs(): HasMany
    {
        return $this->hasMany(SearchLogs::class, "user_id");
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscriptions::class, "user_id");
    }

    public function pointLogs(): HasMany
    {
        return $this->hasMany(PointLogs::class, "user_id");
    }

    public function b2bVerifications(): HasMany
    {
        return $this->hasMany(B2bVerifications::class, "user_id");
    }

    public function b2bQuotes(): HasMany
    {
        return $this->hasMany(B2bQuotes::class, "user_id");
    }

    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSessions::class, "user_id");
    }

    public function apiRateLimits(): HasMany
    {
        return $this->hasMany(ApiRateLimits::class, "user_id");
    }

}
