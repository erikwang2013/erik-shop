<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\model\Carts;
use app\model\ProductSkus;
use Webman\Http\Request;

/**
 * @Apidoc\Group("cart")
 * @Apidoc\Sort(3)
 */
class CartController extends \app\controller\BaseApiController
{
    /**
 * @Apidoc\Title("购物车列表")
 * @Apidoc\Desc("当前用户购物车")
 * @Apidoc\Method("GET")
 * @Apidoc\Url("/api/cart")
 * @Apidoc\Author("erik")
 */
    public function index(Request $request): \support\Response
    {
        $userId = $request->userId;

        $items = Carts::where('user_id', $userId)
            ->with(['sku.product.translation', 'sku.prices'])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($cart) use ($request) {
                $currencyCode = $request->input('currency', 'USD');
                $sku = $cart->sku;
                $price = $sku->prices->where('currency_code', $currencyCode)->first();
                return [
                    'id' => $cart->id,
                    'sku_id' => $sku->id,
                    'product_id' => $sku->product_id,
                    'title' => $sku->product->title ?? '',
                    'image' => $sku->image ?: ($sku->product->main_image ?? ''),
                    'attrs' => $sku->attrs,
                    'price' => $price ? $price->price : $sku->default_price,
                    'currency' => $currencyCode,
                    'quantity' => $cart->quantity,
                    'selected' => (bool) $cart->selected,
                    'stock' => $sku->stock,
                ];
            });

        return ApiResponse::success($items);
    }

    /**
 * @Apidoc\Title("添加购物车")
 * @Apidoc\Method("POST")
 * @Apidoc\Url("/api/cart")
 * @Apidoc\Author("erik")
 * @Apidoc\Param(name="sku_id", type="string", require=true, desc="SKU ID")
 * @Apidoc\Param(name="quantity", type="int", require=false, default=1, desc="数量")
 */
    public function store(Request $request): \support\Response
    {
        $userId = $request->userId;
        $skuId = $request->input('sku_id');
        $quantity = max(1, (int) $request->input('quantity', 1));

        $sku = ProductSkus::find($skuId);
        if (!$sku || $sku->status !== 1) {
            return ApiResponse::fail('SKU不存在或已下架', 404);
        }

        if ($sku->stock < $quantity) {
            return ApiResponse::fail('库存不足', 422);
        }

        $existing = Carts::where('user_id', $userId)->where('sku_id', $skuId)->first();
        if ($existing) {
            $newQty = $existing->quantity + $quantity;
            if ($sku->stock < $newQty) {
                return ApiResponse::fail('库存不足', 422);
            }
            $existing->quantity = $newQty;
            $existing->save();
        } else {
            Carts::create([
                'user_id' => $userId,
                'sku_id' => $skuId,
                'product_id' => $sku->product_id,
                'quantity' => $quantity,
            ]);
        }

        return ApiResponse::success(null, '已添加到购物车');
    }

    /**
 * @Apidoc\Title("更新数量")
 * @Apidoc\Method("PUT")
 * @Apidoc\Url("/api/cart/{id}")
 * @Apidoc\Author("erik")
 * @Apidoc\Param(name="quantity", type="int", require=true, desc="数量")
 */
    public function update(Request $request, string $id): \support\Response
    {
        $userId = $request->userId;
        $quantity = max(0, (int) $request->input('quantity', 1));

        $cart = Carts::where('id', $id)->where('user_id', $userId)->first();
        if (!$cart) {
            return ApiResponse::fail('购物车记录不存在', 404);
        }

        if ($quantity === 0) {
            $cart->delete();
            return ApiResponse::success(null, '已移除');
        }

        $sku = ProductSkus::find($cart->sku_id);
        if ($sku && $sku->stock < $quantity) {
            return ApiResponse::fail('库存不足', 422);
        }

        $cart->quantity = $quantity;
        $cart->save();

        return ApiResponse::success(null, '已更新');
    }

    /**
 * @Apidoc\Title("删除")
 * @Apidoc\Method("DELETE")
 * @Apidoc\Url("/api/cart/{id}")
 * @Apidoc\Author("erik")
 */
    public function destroy(Request $request, string $id): \support\Response
    {
        $userId = $request->userId;

        Carts::where('id', $id)->where('user_id', $userId)->delete();

        return ApiResponse::success(null, '已移除');
    }
}
