<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\TariffRules;
use app\model\VatSettings;
use app\model\ProductHsCodes;
use Webman\Http\Request;
class TariffController extends \app\controller\BaseApiController
{
    public function estimate(Request $request): \support\Response {
        $pid = $request->input('product_id'); $did = $request->input('dest_country_id'); $dv = (float)$request->input('declared_value',0);
        $hsIds = ProductHsCodes::where('product_id',$pid)->pluck('hs_code_id');
        $rule = TariffRules::where('dest_country_id',$did)->whereIn('hs_code_id',$hsIds)->first();
        $vat = VatSettings::where('country_id',$did)->first();
        $dr = (float)($rule->duty_rate??0); $vr = (float)($vat->vat_rate??0);
        $dft = (float)($vat->duty_free_threshold??0); $vft = (float)($vat->vat_free_threshold??0);
        $duty = $dv>=$dft ? round($dv*$dr/100,2) : 0;
        $vatVal = ($dv+$duty)>=$vft ? round(($dv+$duty)*$vr/100,2) : 0;
        return ApiResponse::success(['duty_rate'=>$dr,'vat_rate'=>$vr,'estimated_duty'=>$duty,'estimated_vat'=>$vatVal,'estimated_total'=>$duty+$vatVal,'is_estimate'=>true,'disclaimer'=>'仅供参考，实际以海关核定为准']);
    }
}
