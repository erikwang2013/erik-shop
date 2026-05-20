<?php
namespace plugin\admin\app\controller\shop;
use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\Products;
use plugin\admin\app\model\shop\Categories;

class ShopProductController extends Crud
{
    protected $model = Products::class;

    protected function afterQuery($items)
    {
        $items->load(["skus", "images", "translations"]);
        foreach ($items as $item) {
            $item->category_name = Categories::find($item->category_id)->name ?? "";
        }
        return $items;
    }
}
