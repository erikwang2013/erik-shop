<?php
namespace plugin\admin\app\controller\shop;
use app\common\Cdn;
use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\Products;
use plugin\admin\app\model\shop\Categories;
use plugin\admin\app\model\shop\ProductSkus;
use support\Request;
use support\Response;

/**
 * @Apidoc\Group("product")
 * @Apidoc\Sort(1)
 */
class ShopProductController extends Crud
{
    protected $model = Products::class;

    /**
     * 更新商品：改图后失效旧/新 main_image 的 CDN 缓存
     */
    public function update(Request $request): Response
    {
        [$id, $data] = $this->updateInput($request);
        $old = (string) ($this->model->where('id', $id)->value('main_image') ?: '');
        $this->doUpdate($id, $data);
        $new = (string) ($data['main_image'] ?? '');
        Cdn::purge(array_values(array_filter([$old, $new])));
        return $this->json(0);
    }

    /**
     * 删除商品：先取主图列表，删除后失效 CDN 缓存
     */
    public function delete(Request $request): Response
    {
        $ids = $this->deleteInput($request);
        $images = $this->model->whereIn('id', $ids)->pluck('main_image')->filter()->values()->all();
        $this->doDelete($ids);
        Cdn::purge($images);
        return $this->json(0);
    }

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
