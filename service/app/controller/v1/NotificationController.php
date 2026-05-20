<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\Notifications;
use Webman\Http\Request;

class NotificationController extends \app\controller\BaseApiController
{
    public function index(Request $request): \support\Response
    {
        $page = (int) $request->input('page', 1);
        $perPage = min((int) $request->input('per_page', 20), 50);

        $paginator = Notifications::where(function ($q) use ($request) {
            $q->where('user_id', $request->userId)->orWhere('user_id', 0);
        })->orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginate($paginator->items(), $paginator->total(), $page, $perPage);
    }

    public function read(Request $request, string $id): \support\Response
    {
        Notifications::where('id', $id)
            ->where(function ($q) use ($request) {
                $q->where('user_id', $request->userId)->orWhere('user_id', 0);
            })
            ->update(['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')]);

        return ApiResponse::success(null, '已读');
    }
}
