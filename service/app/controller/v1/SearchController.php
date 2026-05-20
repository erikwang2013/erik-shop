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

        // TODO: 集成 Elasticsearch via webman-scout
        // $results = Products::search($keyword)->where('status', 2)->paginate($perPage);

        // 降级：MySQL LIKE 搜索
        $query = Products::where('status', 2)
            ->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhere('description', 'like', "%{$keyword}%")
                  ->orWhere('brand', 'like', "%{$keyword}%");
            });

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $paginator = $query->orderBy('sales_count', 'desc')->paginate($perPage, ['*'], 'page', $page);

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
