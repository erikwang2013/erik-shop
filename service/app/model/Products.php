<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Erikwang2013\WebmanScout\Searchable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Products extends BaseModel
{
    protected $connection = 'mysql_rw';   // 读写分离：读走 read 副本（sticky 写后读主库）
    use SoftDeletes;
    use Searchable;

    protected $table = "shop_products";

    // ES搜索字段
    protected $searchable = [
        'title', 'subtitle', 'description', 'brand',
    ];

    /**
     * 转换为ES索引格式
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'brand' => $this->brand,
            'description' => strip_tags($this->description ?? ''),
            'min_price' => (float) $this->min_price,
            'max_price' => (float) $this->max_price,
            'status' => $this->status,
            'status_text' => ['草稿','待审','已上架','已下架'][$this->status] ?? '',
            'sales_count' => $this->sales_count,
            'view_count' => $this->view_count,
            'is_hot' => (bool) $this->is_hot,
            'is_new' => (bool) $this->is_new,
            'is_recommend' => (bool) $this->is_recommend,
            'weight' => (float) $this->weight,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            // 多语言字段（zh_CN/zh_HK/en/ja/ko），搜索时按当前 locale 命中对应语言内容
            'translations' => $this->translation->map(fn ($t) => [
                'locale' => $t->locale,
                'title' => $t->title,
                'subtitle' => $t->subtitle,
                'description' => $t->description,
            ])->values()->all(),
        ];
    }

    /**
     * 批量导入 ES 时预加载多语言，避免 toSearchableArray 内 N+1 查询
     */
    protected function makeAllSearchableUsing($query)
    {
        return $query->with('translation');
    }

    /**
     * ES索引名
     */
    public function searchableAs(): string
    {
        return 'shop_products';
    }

    /**
     * ES索引映射
     */
    public static function getSearchMapping(): array
    {
        return [
            'properties' => [
                'id' => ['type' => 'long'],
                'category_id' => ['type' => 'long'],
                'title' => ['type' => 'text', 'analyzer' => 'multilingual', 'fields' => ['keyword' => ['type' => 'keyword']]],
                'subtitle' => ['type' => 'text', 'analyzer' => 'multilingual'],
                'brand' => ['type' => 'keyword'],
                'description' => ['type' => 'text', 'analyzer' => 'multilingual'],
                'min_price' => ['type' => 'float'],
                'max_price' => ['type' => 'float'],
                'status' => ['type' => 'integer'],
                'status_text' => ['type' => 'keyword'],
                'sales_count' => ['type' => 'integer'],
                'view_count' => ['type' => 'integer'],
                'is_hot' => ['type' => 'boolean'],
                'is_new' => ['type' => 'boolean'],
                'is_recommend' => ['type' => 'boolean'],
                'weight' => ['type' => 'float'],
                'created_at' => ['type' => 'date'],
                'translations' => [
                    'type' => 'object',
                    'properties' => [
                        'locale' => ['type' => 'keyword'],
                        'title' => ['type' => 'text', 'analyzer' => 'multilingual'],
                        'subtitle' => ['type' => 'text', 'analyzer' => 'multilingual'],
                        'description' => ['type' => 'text', 'analyzer' => 'multilingual'],
                    ],
                ],
            ],
        ];
    }

    // ===== 关联关系 =====
    public function category()
    {
        return $this->belongsTo(Categories::class, 'category_id');
    }

    public function skus(): HasMany
    {
        return $this->hasMany(ProductSkus::class, 'product_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImages::class, 'product_id');
    }

    public function translation(): HasMany
    {
        return $this->hasMany(ProductTranslations::class, 'product_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReviews::class, 'product_id');
    }

    public function compliance(): HasMany
    {
        return $this->hasMany(ProductCompliance::class, 'product_id');
    }

    public function hsCodes(): HasMany
    {
        return $this->hasMany(ProductHsCodes::class, 'product_id');
    }
}
