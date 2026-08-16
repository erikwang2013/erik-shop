<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\CookieConsents;
use app\model\PrivacyRequests;
use Webman\Http\Request;

class PrivacyController extends \app\controller\BaseApiController
{
    public function index(Request $request): \support\Response
    {
        $page = (int) $request->input('page', 1);
        $perPage = min((int) $request->input('per_page', 10), 50);
        $paginator = PrivacyRequests::where('user_id', $request->userId)->orderBy('id','desc')->paginate($perPage, ['*'], 'page', $page);
        return ApiResponse::paginate($paginator->items(), $paginator->total(), $page, $perPage);
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

    /**
     * 提交 Cookie 偏好（GDPR/CCPA Consent）
     * POST /api/privacy/cookie-consent
     * {preferences: {necessary:true, analytics:bool, marketing:bool}, session_id?}
     */
    public function cookieConsent(Request $request): \support\Response
    {
        $preferences = $request->input('preferences', []);
        $sessionId = (string) $request->input('session_id', '');
        if (!is_array($preferences) || empty($preferences)) {
            return ApiResponse::fail('preferences 不能为空', 422);
        }

        $version = (string) config('privacy.cookie_consent.version', '1.0');
        $userId = $request->userId ?? 0;

        CookieConsents::create([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'version' => $version,
            'preferences' => json_encode($preferences),
            'ip_address' => $request->getRealIp(),
        ]);

        return ApiResponse::success(['version' => $version], '已记录 Cookie 偏好');
    }
}
