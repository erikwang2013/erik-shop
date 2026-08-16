<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\Users;
use app\model\UserAddresses;
use Webman\Http\Request;

class UserController extends \app\controller\BaseApiController
{
    public function profile(Request $request): \support\Response
    {
        $user = Users::select(['id','nickname','avatar','email','mobile','sex','birthday','money','score','level','status','invite_code','last_login_at'])->find($request->userId);
        return $user ? ApiResponse::success($user) : ApiResponse::fail('用户不存在', 404);
    }

    public function updateProfile(Request $request): \support\Response
    {
        $user = Users::find($request->userId);
        if (!$user) return ApiResponse::fail('用户不存在', 404);
        $data = $request->only(['nickname','avatar','sex','birthday']);
        $user->fill(array_filter($data))->save();
        return ApiResponse::success($user, '更新成功');
    }

    public function addresses(Request $request): \support\Response
    {
        $list = UserAddresses::where('user_id', $request->userId)->get();
        return ApiResponse::success($list);
    }

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

    public function updateAddress(Request $request, string $id): \support\Response
    {
        $id = $this->decodedId($id);
        $addr = UserAddresses::where('id',$id)->where('user_id',$request->userId)->first();
        if (!$addr) return ApiResponse::fail('地址不存在', 404);
        $data = $request->only(['name','phone','country_id','province','city','district','detail','postal_code','is_default','tag']);
        if ($request->input('is_default')) {
            UserAddresses::where('user_id', $request->userId)->update(['is_default' => 0]);
        }
        $addr->fill(array_filter($data))->save();
        return ApiResponse::success($addr, '更新成功');
    }

    public function deleteAddress(Request $request, string $id): \support\Response
    {
        $id = $this->decodedId($id);
        UserAddresses::where('id',$id)->where('user_id',$request->userId)->delete();
        return ApiResponse::success(null, '已删除');
    }

    public function updateLocale(Request $request): \support\Response
    {
        return ApiResponse::success(['locale'=>$request->input('locale','en'),'currency'=>$request->input('currency','USD')], '已更新');
    }
}
