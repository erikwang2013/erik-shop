<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\GroupBuys;
use Webman\Http\Request;
class GroupBuyController extends \app\controller\BaseApiController
{
    public function index(Request $request): \support\Response {
        $list = GroupBuys::where('status',1)->with(['sku.product'])->get()->map(fn($g)=>['id'=>$g->id,'title'=>$g->title,'group_price'=>$g->group_price,'required_count'=>$g->required_count]);
        return ApiResponse::success($list);
    }
}
