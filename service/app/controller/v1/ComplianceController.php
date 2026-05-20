<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\CountryComplianceRules;
use app\model\ProductCompliance;
use Webman\Http\Request;

class ComplianceController extends \app\controller\BaseApiController
{
    public function check(Request $request): \support\Response
    {
        $productId = $request->input('product_id');
        $destCountryId = $request->input('dest_country_id');

        $productCompliances = ProductCompliance::where('product_id', $productId)->pluck('compliance_category_id');
        if ($productCompliances->isEmpty()) {
            return ApiResponse::success(['overall' => 'allowed', 'items' => [], 'message' => '无特殊合规要求']);
        }

        $rules = CountryComplianceRules::where('country_id', $destCountryId)
            ->whereIn('compliance_category_id', $productCompliances)
            ->with('complianceCategory')->get();

        $result = ['overall' => 'allowed', 'items' => []];
        foreach ($rules as $rule) {
            if ($rule->rule === 'prohibited') $result['overall'] = 'prohibited';
            elseif ($rule->rule === 'restricted' && $result['overall'] === 'allowed') $result['overall'] = 'restricted';
            $result['items'][] = [
                'category' => $rule->complianceCategory->name ?? '',
                'status' => $rule->rule,
                'reason' => $rule->restriction_reason ?? '',
            ];
        }
        return ApiResponse::success($result);
    }
}
