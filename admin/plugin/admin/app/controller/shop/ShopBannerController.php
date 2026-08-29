<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\controller\shop;

use app\common\Cdn;
use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\Banners;
use support\Request;
use support\Response;

/**
 * @Apidoc\Group("banner")
 * @Apidoc\Sort(17)
 */
class ShopBannerController extends Crud
{
    protected $model = Banners::class;

    /**
     * 更新轮播图：改图后失效旧/新 image 的 CDN 缓存
     */
    public function update(Request $request): Response
    {
        [$id, $data] = $this->updateInput($request);
        $old = (string) ($this->model->where('id', $id)->value('image') ?: '');
        $this->doUpdate($id, $data);
        $new = (string) ($data['image'] ?? '');
        Cdn::purge(array_values(array_filter([$old, $new])));
        return $this->json(0);
    }

    /**
     * 删除轮播图：先取图片列表，删除后失效 CDN 缓存
     */
    public function delete(Request $request): Response
    {
        $ids = $this->deleteInput($request);
        $images = $this->model->whereIn('id', $ids)->pluck('image')->filter()->values()->all();
        $this->doDelete($ids);
        Cdn::purge($images);
        return $this->json(0);
    }
}
