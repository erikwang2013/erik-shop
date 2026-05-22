<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\ShippingZones;
use app\model\ShippingZoneRates;
use app\model\Countries;
use Webman\Http\Request;
class ShippingController extends \app\controller\BaseApiController
{
    public function calculate(Request $request): \support\Response {
        $country = Countries::find($request->input('dest_country_id'));
        if(!$country) return ApiResponse::fail('国家不存在',404);
        $zone = ShippingZones::where('status',1)->whereJsonContains('countries',$country->iso_code_2)->first();
        if(!$zone) return ApiResponse::fail('暂不支持配送',422);
        $weight = ((int)$request->input('weight',500))/1000;
        $rates = ShippingZoneRates::where('zone_id',$zone->id)->where('weight_from','<=',$weight)->where(function($q)use($weight){$q->where('weight_to','>=',$weight)->orWhereNull('weight_to');})->with('logistics')->get()->map(fn($r)=>['logistics_name'=>$r->logistics->name??'','logistics_code'=>$r->logistics->code??'','fee'=>round($r->price+$weight*(float)$r->per_kg_price,2),'estimated_days'=>$r->logistics->estimated_days??'7-15']);
        return ApiResponse::success(['zone_name'=>$zone->name,'weight_kg'=>round($weight,3),'dest_country'=>$country->iso_code_2,'options'=>$rates]);
    }
}
