<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\PrivacyRequests;
use Webman\Http\Request;

class PrivacyController extends \app\controller\BaseApiController
{
    public function index(Request $request): \support\Response
    {
        $list = PrivacyRequests::where('user_id', $request->userId)->orderBy('id','desc')->get();
        return ApiResponse::success($list);
    }

    public function create(Request $request): \support\Response
    {
        $type = $request->input('type');  // data_access/data_delete/opt_out/data_portability
        if (!in_array($type, ['data_access','data_delete','opt_out','data_portability'])) {
            return ApiResponse::fail('无效的请求类型', 422);
        }

        PrivacyRequests::create([
            'user_id' => $request->userId,
            'type' => $type,
            'status' => 'pending',
        ]);

        return ApiResponse::success(null, '隐私请求已提交，我们将在30天内处理');
    }
}
