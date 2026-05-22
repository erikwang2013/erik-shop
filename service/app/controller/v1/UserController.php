<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\Users;
use app\model\UserAddresses;
use Webman\Http\Request;

/**
 * @Apidoc\Group("user")
 * @Apidoc\Sort(6)
 */
class UserController extends \app\controller\BaseApiController
{
    /**
 * @Apidoc\Title("个人信息")
 * @Apidoc\Method("GET")
 * @Apidoc\Url("/api/user/profile")
 * @Apidoc\Author("erik")
 */
    public function profile(Request $request): \support\Response
    {
        $user = Users::select(['id','nickname','avatar','email','mobile','sex','birthday','money','score','level','status','invite_code','last_login_at'])->find($request->userId);
        return $user ? ApiResponse::success($user) : ApiResponse::fail('用户不存在', 404);
    }

    /**
 * @Apidoc\Title("更新信息")
 * @Apidoc\Method("PUT")
 * @Apidoc\Url("/api/user/profile")
 * @Apidoc\Author("erik")
 * @Apidoc\Param(name="nickname", type="string", require=false)
 * @Apidoc\Param(name="avatar", type="string", require=false)
 * @Apidoc\Param(name="sex", type="int", require=false)
 * @Apidoc\Param(name="birthday", type="string", require=false)
 */
    public function updateProfile(Request $request): \support\Response
    {
        $user = Users::find($request->userId);
        if (!$user) return ApiResponse::fail('用户不存在', 404);
        $data = $request->only(['nickname','avatar','sex','birthday']);
        $user->fill(array_filter($data))->save();
        return ApiResponse::success($user, '更新成功');
    }

    /**
 * @Apidoc\Title("地址列表")
 * @Apidoc\Method("GET")
 * @Apidoc\Url("/api/user/addresses")
 * @Apidoc\Author("erik")
 */
    public function addresses(Request $request): \support\Response
    {
        $list = UserAddresses::where('user_id', $request->userId)->get();
        return ApiResponse::success($list);
    }

    /**
 * @Apidoc\Title("新增地址")
 * @Apidoc\Method("POST")
 * @Apidoc\Url("/api/user/addresses")
 * @Apidoc\Author("erik")
 * @Apidoc\Param(name="name", type="string", require=true, desc="收件人")
 * @Apidoc\Param(name="phone", type="string", require=true, desc="电话")
 * @Apidoc\Param(name="country_id", type="string", require=true, desc="国家ID")
 * @Apidoc\Param(name="detail", type="string", require=true, desc="详细地址")
 */
    public function createAddress(Request $request): \support\Response
    {
        $data = $request->only(['name','phone','country_id','province','city','district','detail','postal_code','is_default','tag']);
        $data['user_id'] = $request->userId;
        if ($request->input('is_default')) {
            UserAddresses::where('user_id', $request->userId)->update(['is_default' => 0]);
        }
        $addr = UserAddresses::create($data);
        return ApiResponse::success($addr, '添加成功');
    }

    /**
 * @Apidoc\Title("更新地址")
 * @Apidoc\Method("PUT")
 * @Apidoc\Url("/api/user/addresses/{id}")
 * @Apidoc\Author("erik")
 */
    public function updateAddress(Request $request, string $id): \support\Response
    {
        $addr = UserAddresses::where('id',$id)->where('user_id',$request->userId)->first();
        if (!$addr) return ApiResponse::fail('地址不存在', 404);
        $data = $request->only(['name','phone','country_id','province','city','district','detail','postal_code','is_default','tag']);
        if ($request->input('is_default')) {
            UserAddresses::where('user_id', $request->userId)->update(['is_default' => 0]);
        }
        $addr->fill(array_filter($data))->save();
        return ApiResponse::success($addr, '更新成功');
    }

    /**
 * @Apidoc\Title("删除地址")
 * @Apidoc\Method("DELETE")
 * @Apidoc\Url("/api/user/addresses/{id}")
 * @Apidoc\Author("erik")
 */
    public function deleteAddress(Request $request, string $id): \support\Response
    {
        UserAddresses::where('id',$id)->where('user_id',$request->userId)->delete();
        return ApiResponse::success(null, '已删除');
    }

    /**
 * @Apidoc\Title("语言/币种")
 * @Apidoc\Method("PUT")
 * @Apidoc\Url("/api/user/locale")
 * @Apidoc\Author("erik")
 * @Apidoc\Param(name="locale", type="string", require=false, default="en")
 * @Apidoc\Param(name="currency", type="string", require=false, default="USD")
 */
    public function updateLocale(Request $request): \support\Response
    {
        return ApiResponse::success(['locale'=>$request->input('locale','en'),'currency'=>$request->input('currency','USD')], '已更新');
    }
}
