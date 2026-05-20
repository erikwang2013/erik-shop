<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\Users;
use app\model\MembershipLevels;
use app\model\MembershipBenefits;
use app\model\PointLogs;
use Webman\Http\Request;

class MembershipController extends \app\controller\BaseApiController
{
    public function index(Request $request): \support\Response
    {
        $user = Users::find($request->userId);
        $level = MembershipLevels::where('level', $user->level ?? 0)->first();
        $benefits = MembershipBenefits::where('level_id', $level->id ?? 0)->get();
        $levels = MembershipLevels::where('status',1)->orderBy('level')->get();

        return ApiResponse::success([
            'current_level' => $level,
            'current_benefits' => $benefits,
            'all_levels' => $levels,
            'current_score' => (int) ($user->score ?? 0),
        ]);
    }

    public function points(Request $request): \support\Response
    {
        $page = (int) $request->input('page', 1);
        $paginator = PointLogs::where('user_id', $request->userId)->orderBy('id','desc')->paginate(20, ['*'], 'page', $page);
        return ApiResponse::paginate($paginator->items(), $paginator->total(), $page, 20);
    }
}
