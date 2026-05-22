<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\Users;
use app\model\UserSocialAccounts;
use Webman\Http\Request;
class SocialAuthController extends \app\controller\BaseApiController
{
    public function login(Request $request): \support\Response {
        $provider = $request->input('provider'); $puid = $request->input('provider_user_id'); $email = $request->input('email',''); $name = $request->input('name','');
        if(!in_array($provider,['google','apple','facebook'])) return ApiResponse::fail('不支持的平台',422);
        $social = UserSocialAccounts::where('provider',$provider)->where('provider_user_id',$puid)->first();
        if($social){ $user = Users::find($social->user_id); if($user) return ApiResponse::success(['user_id'=>$user->id,'nickname'=>$user->nickname,'email'=>$user->email,'is_new'=>false],'登录成功'); }
        $user = $email ? Users::where('email',$email)->first() : null;
        if($user){ UserSocialAccounts::create(['user_id'=>$user->id,'provider'=>$provider,'provider_user_id'=>$puid]); return ApiResponse::success(['user_id'=>$user->id,'nickname'=>$user->nickname,'email'=>$user->email,'is_new'=>false],'登录成功'); }
        $user = Users::create(['nickname'=>$name?:ucfirst($provider).'User','email'=>$email,'password'=>'','salt'=>bin2hex(random_bytes(3)),'invite_code'=>strtoupper(substr(md5(uniqid()),0,8)),'status'=>1]);
        UserSocialAccounts::create(['user_id'=>$user->id,'provider'=>$provider,'provider_user_id'=>$puid]);
        return ApiResponse::success(['user_id'=>$user->id,'nickname'=>$user->nickname,'email'=>$user->email,'is_new'=>true],'注册成功');
    }
}
