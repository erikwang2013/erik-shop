<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\Products;
use app\model\SearchLogs;
use Webman\Http\Request;

class SearchController extends \app\controller\BaseApiController
{
    public function index(Request $request): \support\Response
    {
        $keyword = $request->input('keyword', '');
        $page = (int) $request->input('page', 1);
        $perPage = min((int) $request->input('per_page', 20), 100);
        $categoryId = $request->input('category_id');
        $locale = $request->locale ?? 'en';

        if (empty(trim($keyword))) {
            return ApiResponse::fail('请输入搜索关键词', 422);
        }

        // ES 搜索（erikwang2013/webman-scout）：未配置 ELASTICSEARCH_HOST 或查询异常时降级到 MySQL LIKE
        $esHosts = config('plugin.erikwang2013.webman-scout.app.elasticsearch.hosts', []);
        try {
            if (empty($esHosts)) {
                throw new \RuntimeException('ES 未配置');
            }
            $esResults = Products::search($keyword)->where('status', 2);
            if ($categoryId) $esResults->where('category_id', (int) $categoryId);
            $paginator = $esResults->paginate($perPage, 'page', $page);
        } catch (\Throwable $e) {
            $query = Products::where('status', 2)
                ->where(function ($q) use ($keyword) {
                    $q->where('title', 'like', "%{$keyword}%")
                      ->orWhere('description', 'like', "%{$keyword}%")
                      ->orWhere('brand', 'like', "%{$keyword}%");
                });
            if ($categoryId) $query->where('category_id', (int) $categoryId);
            $paginator = $query->orderBy('sales_count', 'desc')->paginate($perPage, ['*'], 'page', $page);
        }

        // 记录搜索日志
        SearchLogs::create([
            'user_id' => $request->userId ?? 0,
            'keyword' => $keyword,
            'result_count' => $paginator->total(),
            'locale' => $locale,
        ]);

        return ApiResponse::paginate($paginator->items(), $paginator->total(), $page, $perPage);
    }
}
