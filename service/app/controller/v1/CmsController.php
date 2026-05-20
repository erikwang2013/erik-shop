<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\CmsPages;
use app\model\CmsPageTranslations;
use Webman\Http\Request;

class CmsController extends \app\controller\BaseApiController
{
    public function show(Request $request, string $slug): \support\Response
    {
        $locale = $request->locale ?? 'en';
        $page = CmsPages::where('slug', $slug)->where('status', 1)->first();
        if (!$page) return ApiResponse::fail('页面不存在', 404);

        $translation = CmsPageTranslations::where('page_id', $page->id)->where('locale', $locale)->first();
        return ApiResponse::success([
            'title' => $translation->title ?? '',
            'content' => $translation->content ?? '',
            'meta_title' => $translation->meta_title ?? '',
            'meta_description' => $translation->meta_description ?? '',
        ]);
    }
}
