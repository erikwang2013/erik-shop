<?php
namespace plugin\admin\app\controller\shop;
use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\Products;
use plugin\admin\app\model\shop\Categories;
use plugin\admin\app\model\shop\ProductSkus;

/**
 * @Apidoc\Group("product")
 * @Apidoc\Sort(1)
 */
class ShopProductController extends Crud
{
    protected $model = Products::class;

    protected function afterQuery($items)
    {
        if ($items->isEmpty()) {
            return $items;
        }
        $skuStock = ProductSkus::whereIn('product_id', $items->pluck('id'))
            ->selectRaw('product_id, SUM(stock) AS stock')->groupBy('product_id')
            ->pluck('stock', 'product_id');
        $catNames = Categories::whereIn('id', $items->pluck('category_id')->filter()->unique())
            ->pluck('name', 'id');
        foreach ($items as $item) {
            $item->stock = (int)($skuStock[$item->id] ?? 0);
            $item->category_name = $catNames[$item->category_id] ?? "";
        }
        return $items;
    }
}
