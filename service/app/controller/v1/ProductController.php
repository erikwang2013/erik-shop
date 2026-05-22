<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\model\Products;
use app\model\ProductSkus;
use app\model\ProductTranslations;
use app\model\ProductSkuPrices;
use app\model\Categories;
use app\model\Countries;
use app\model\ExchangeRates;
use app\model\VatSettings;
use Webman\Http\Request;

/**
 * 商品控制器
 */
/**
 * @Apidoc\Group("product")
 * @Apidoc\Sort(2)
 */
class ProductController extends \app\controller\BaseApiController
{
    /**
 * @Apidoc\Title("商品列表")
 * @Apidoc\Desc("分页+筛选+排序")
 * @Apidoc\Method("GET")
 * @Apidoc\Url("/api/products")
 * @Apidoc\Author("erik")
 * @Apidoc\Param(name="page", type="int", require=false, default=1, desc="页码")
 * @Apidoc\Param(name="per_page", type="int", require=false, default=20, desc="每页(max100)")
 * @Apidoc\Param(name="category_id", type="string", require=false, desc="分类ID")
 * @Apidoc\Param(name="keyword", type="string", require=false, desc="搜索")
 * @Apidoc\Param(name="sort", type="string", require=false, default="default", desc="排序")
 * @Apidoc\Returned(name="list", type="array", desc="商品列表")
 * @Apidoc\Returned(name="total", type="int", desc="总数")
 */
    public function index(Request $request): \support\Response
    {
        $page = (int) $request->input('page', 1);
        $perPage = min((int) $request->input('per_page', 20), 100);
        $categoryId = $request->input('category_id');
        $keyword = $request->input('keyword', '');
        $sort = $request->input('sort', 'default');  // default/price_asc/price_desc/sales/newest
        $status = $request->input('status', 2);       // 默认只查已上架
        $locale = $request->locale ?? 'en';

        $query = Products::where('status', $status);

        // 分类筛选（含子分类）
        if ($categoryId) {
            $childIds = Categories::where('parent_id', $categoryId)->pluck('id')->toArray();
            $allIds = array_merge([$categoryId], $childIds);
            $query->whereIn('category_id', $allIds);
        }

        // 多语言搜索
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhereHas('translation', function ($t) use ($keyword) {
                      $t->where('title', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%");
                  });
            });
        }

        // 排序
        $query = match ($sort) {
            'price_asc' => $query->orderBy('min_price', 'asc'),
            'price_desc' => $query->orderBy('min_price', 'desc'),
            'sales' => $query->orderBy('sales_count', 'desc'),
            'newest' => $query->orderBy('id', 'desc'),
            default => $query->orderBy('sort', 'desc')->orderBy('id', 'desc'),
        };

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = $paginator->items();
        foreach ($items as $product) {
            $product->makeHidden(['description', 'deleted_at']);
            // 加载当前语言翻译
            $translation = ProductTranslations::where('product_id', $product->id)
                ->where('locale', $locale)->first();
            if ($translation) {
                $product->title = $translation->title ?: $product->title;
                $product->subtitle = $translation->subtitle ?: $product->subtitle;
            }
        }

        return ApiResponse::paginate($items, $paginator->total(), $page, $perPage);
    }

    /**
 * @Apidoc\Title("商品详情")
 * @Apidoc\Desc("含SKU/多语言/多币种/合规/HS")
 * @Apidoc\Method("GET")
 * @Apidoc\Url("/api/products/{id}")
 * @Apidoc\Author("erik")
 * @Apidoc\Param(name="currency", type="string", require=false, default="USD", desc="币种")
 * @Apidoc\Param(name="dest_country", type="string", require=false, default="US", desc="目的国ISO2")
 * @Apidoc\Returned(name="id", type="string", desc="商品ID")
 * @Apidoc\Returned(name="skus", type="array", desc="SKU(含分币种价格)")
 * @Apidoc\Returned(name="images", type="array", desc="图片")
 */
    public function show(Request $request, string $id): \support\Response
    {
        $locale = $request->locale ?? 'en';
        $currencyCode = $request->input('currency', 'USD');
        $destCountryCode = $request->input('dest_country', 'US');

        $product = Products::with(['skus.prices', 'images', 'hsCodes.hsCode', 'compliance'])
            ->find($id);

        if (!$product || $product->status < 1) {
            return ApiResponse::fail('商品不存在或已下架', 404);
        }

        // 多语言内容
        $translation = ProductTranslations::where('product_id', $product->id)
            ->where('locale', $locale)->first();
        if ($translation) {
            $product->title = $translation->title ?: $product->title;
            $product->subtitle = $translation->subtitle ?: $product->subtitle;
            $product->description = $translation->description ?: $product->description;
        }

        // 分币种价格
        $destCountry = Countries::where('iso_code_2', $destCountryCode)->first();
        foreach ($product->skus as $sku) {
            $currencyPrice = $sku->prices->where('currency_code', $currencyCode)->first();
            $basePrice = $currencyPrice
                ? $currencyPrice->price
                : $this->convertPrice($sku->default_price, 'CNY', $currencyCode);

            $vatRate = VatSettings::where('country_id', $destCountry->id)->value('vat_rate') ?? 0;
            $sku->display_price = [
                'tax_exclusive' => round($basePrice, 2),
                'tax_inclusive' => round($basePrice * (1 + $vatRate / 100), 2),
                'vat_amount' => round($basePrice * $vatRate / 100, 2),
                'vat_rate' => $vatRate,
                'currency' => $currencyCode,
                'display_mode' => $destCountry->price_display_mode ?? 'tax_exclusive',
            ];
        }

        // 合规信息
        $product->compliance_info = $product->compliance->map(fn($c) => [
            'category' => $c->complianceCategory->name ?? '',
            'code' => $c->complianceCategory->code ?? '',
            'cert_no' => $c->cert_no,
        ]);

        // HS Code
        $product->hs_codes = $product->hsCodes->map(fn($h) => [
            'code' => $h->hsCode->code ?? '',
            'is_primary' => (bool) $h->is_primary,
        ]);

        $product->increment('view_count');

        return ApiResponse::success($product);
    }

    /**
     * 汇率换算（降级方案）
     */
    private function convertPrice(float $amount, string $from, string $to): float
    {
        if ($from === $to) return $amount;

        $rate = ExchangeRates::where('from_currency', $from)
            ->where('to_currency', $to)
            ->value('rate');

        return $rate ? round($amount * (float) $rate, 2) : $amount;
    }
}
