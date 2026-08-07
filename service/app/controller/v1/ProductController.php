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
 * @Apidoc\Group("product")
 * @Apidoc\Sort(2)
 */
class ProductController extends \app\controller\BaseApiController
{
    /**
     * @Apidoc\Title("商品列表")
     * @Apidoc\Desc("分页获取商品列表，支持分类筛选、关键词搜索、排序")
     * @Apidoc\Method("GET")
     * @Apidoc\Url("/api/products")
     * @Apidoc\Author("erik")
     * @Apidoc\Param(name="page", type="int", require=false, default=1, desc="页码")
     * @Apidoc\Param(name="per_page", type="int", require=false, default=20, desc="每页数量(max 100)")
     * @Apidoc\Param(name="category_id", type="string", require=false, desc="分类ID(hashids)")
     * @Apidoc\Param(name="keyword", type="string", require=false, desc="搜索关键词")
     * @Apidoc\Param(name="sort", type="string", require=false, default="default", desc="default/price_asc/price_desc/sales/newest")
     * @Apidoc\Returned(name="list", type="array", desc="商品列表")
     * @Apidoc\Returned(name="total", type="int", desc="总数")
     */
    public function index(Request $request): \support\Response
    {
        $page = (int) $request->input('page', 1);
        $perPage = min((int) $request->input('per_page', 20), 100);
        $categoryId = $request->input('category_id');
        $keyword = $request->input('keyword', '');
        $sort = $request->input('sort', 'default');
        $locale = $request->locale ?? 'en';
        $query = Products::where('status', 2);
        if ($categoryId) {
            $childIds = Categories::where('parent_id', $categoryId)->pluck('id')->toArray();
            $query->whereIn('category_id', array_merge([$categoryId], $childIds));
        }
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhereHas('translation', fn($t) => $t->where('title','like',"%{$keyword}%"));
            });
        }
        $query = match ($sort) {
            'price_asc'  => $query->orderBy('min_price'),
            'price_desc' => $query->orderBy('min_price', 'desc'),
            'sales'      => $query->orderBy('sales_count', 'desc'),
            'newest'     => $query->orderBy('id', 'desc'),
            default      => $query->orderBy('sort', 'desc')->orderBy('id', 'desc'),
        };
        // 带 locale 约束 eager load，避免每页循环内 N+1 查询
        $paginator = $query->with(['translation' => fn($q) => $q->where('locale', $locale)])
            ->paginate($perPage, ['*'], 'page', $page);
        $items = $paginator->items();
        foreach ($items as $p) {
            $p->makeHidden(['description', 'deleted_at']);
            $t = $p->translation->first();
            if ($t) {
                $p->title = $t->title ?: $p->title;
            }
        }
        return ApiResponse::paginate($items, $paginator->total(), $page, $perPage);
    }

    /**
     * @Apidoc\Title("商品详情")
     * @Apidoc\Desc("获取商品完整信息，含SKU、多语言、分币种价格、合规信息、HS Code")
     * @Apidoc\Method("GET")
     * @Apidoc\Url("/api/products/{id}")
     * @Apidoc\Author("erik")
     * @Apidoc\Param(name="currency", type="string", require=false, default="USD", desc="币种代码")
     * @Apidoc\Param(name="dest_country", type="string", require=false, default="US", desc="目的国ISO2")
     * @Apidoc\Returned(name="id", type="string", desc="商品ID")
     * @Apidoc\Returned(name="title", type="string", desc="商品标题(多语言)")
     * @Apidoc\Returned(name="skus", type="array", desc="SKU列表(含分币种价格)")
     * @Apidoc\Returned(name="images", type="array", desc="商品图片")
     */
    public function show(Request $request, string $id): \support\Response
    {
        $locale = $request->locale ?? 'en';
        $currencyCode = $request->input('currency', 'USD');
        $destCountryCode = $request->input('dest_country', 'US');
        $product = Products::with(['skus.prices', 'images', 'hsCodes.hsCode', 'compliance'])->find($id);
        if (!$product || $product->status !== 2) {
            return ApiResponse::fail('商品不存在或已下架', 404);
        }

        $translation = ProductTranslations::where('product_id', $product->id)
            ->where('locale', $locale)->first();
        if ($translation) {
            $product->title = $translation->title ?: $product->title;
            $product->description = $translation->description ?: $product->description;
        }

        $destCountry = Countries::where('iso_code_2', $destCountryCode)->first();
        // VAT 税率与目的国无关SKU，循环外查一次
        $destCountryId = $destCountry->id ?? null;
        $vatRate = $destCountryId
            ? (VatSettings::where('country_id', $destCountryId)->value('vat_rate') ?? 0)
            : 0;
        foreach ($product->skus as $sku) {
            $cp = $sku->prices->where('currency_code', $currencyCode)->first();
            $bp = $cp ? $cp->price : $this->convertPrice($sku->default_price, 'CNY', $currencyCode);
            $sku->display_price = [
                'tax_exclusive' => round($bp, 2),
                'tax_inclusive' => round($bp * (1 + $vatRate / 100), 2),
                'vat_amount' => round($bp * $vatRate / 100, 2),
                'vat_rate' => $vatRate,
                'currency' => $currencyCode,
                'display_mode' => $destCountry->price_display_mode ?? 'tax_exclusive',
            ];
        }
        $product->compliance_info = $product->compliance->map(fn($c) => [
            'category' => $c->complianceCategory->name ?? '',
            'code' => $c->complianceCategory->code ?? '',
            'cert_no' => $c->cert_no,
        ]);
        $product->hs_codes = $product->hsCodes->map(fn($h) => [
            'code' => $h->hsCode->code ?? '',
            'is_primary' => (bool)$h->is_primary,
        ]);
        $product->increment('view_count');
        return ApiResponse::success($product);
    }

    private function convertPrice(float $amount, string $from, string $to): float
    {
        if ($from === $to) return $amount;
        $rate = ExchangeRates::where('from_currency',$from)->where('to_currency',$to)->value('rate');
        return $rate ? round($amount*(float)$rate,2) : $amount;
    }
}
