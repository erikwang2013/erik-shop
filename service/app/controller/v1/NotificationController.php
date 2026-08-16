<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\Notifications;
use Webman\Http\Request;

class NotificationController extends \app\controller\BaseApiController
{
    public function index(Request $request): \support\Response
    {
        [$page, $perPage] = $this->clampPage($request);

        $paginator = Notifications::where(function ($q) use ($request) {
            $q->where('user_id', $request->userId)->orWhere('user_id', 0);
        })->orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginate($paginator->items(), $paginator->total(), $page, $perPage);
    }

    public function read(Request $request, string $id): \support\Response
    {
        $id = $this->decodedId($id);
        Notifications::where('id', $id)
            ->where(function ($q) use ($request) {
                $q->where('user_id', $request->userId)->orWhere('user_id', 0);
            })
            ->update(['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')]);

        return ApiResponse::success(null, '已读');
    }
}
