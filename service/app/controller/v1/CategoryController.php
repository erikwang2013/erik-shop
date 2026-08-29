<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\common\Cdn;
use app\model\Categories;
use Webman\Http\Request;

/**
 * 商品分类
 */
class CategoryController extends \app\controller\BaseApiController
{
    /**
     * 分类树
     * GET /api/categories
     */
    public function index(Request $request): \support\Response
    {
        $parentId = $request->input('parent_id', 0);

        $categories = Categories::where('parent_id', $parentId)
            ->where('status', 1)
            ->orderBy('sort')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'icon' => Cdn::url($c->icon),
                'image' => Cdn::url($c->image),
                'level' => $c->level,
                'is_hot' => (bool) $c->is_hot,
                'children' => [],  // 前端按需懒加载
            ]);

        return ApiResponse::success($categories);
    }

    /**
     * 完整分类树（含子分类）
     * GET /api/categories/tree
     */
    public function tree(Request $request): \support\Response
    {
        $allCategories = Categories::where('status', 1)
            ->orderBy('sort')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'parent_id' => $c->parent_id,
                'name' => $c->name,
                'slug' => $c->slug,
                'icon' => $c->icon,
                'level' => $c->level,
                'is_hot' => (bool) $c->is_hot,
            ]);

        return ApiResponse::success($this->buildTree($allCategories->toArray()));
    }

    private function buildTree(array $items, int $parentId = 0): array
    {
        $tree = [];
        foreach ($items as $item) {
            if ($item['parent_id'] == $parentId) {
                $item['children'] = $this->buildTree($items, $item['id']);
                $tree[] = $item;
            }
        }
        return $tree;
    }
}
