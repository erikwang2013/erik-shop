<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\model\Countries;
use app\model\Currencies;
use Webman\Http\Request;

/**
 * 国家/货币/汇率数据
 */
class CountryController extends \app\controller\BaseApiController
{
    /**
     * 国家列表（含价格展示规则）
     * GET /api/countries
     */
    public function index(Request $request): \support\Response
    {
        $locale = $request->locale ?? 'en';

        $countries = Countries::where('status', 1)
            ->orderBy('sort')
            ->get()
            ->map(function ($c) use ($locale) {
                return [
                    'id' => $c->id,
                    'name' => $locale === 'zh_CN' ? $c->name_cn : $c->name_en,
                    'iso_code_2' => $c->iso_code_2,
                    'iso_code_3' => $c->iso_code_3,
                    'phone_code' => $c->phone_code,
                    'currency_code' => $c->currency_code,
                    'flag_emoji' => $c->flag_emoji,
                    'timezone' => $c->timezone,
                    'price_display_mode' => $c->price_display_mode,
                    'kyc_required' => (bool) $c->kyc_required,
                ];
            });

        $currencies = Currencies::where('status', 1)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'symbol' => $c->symbol,
            ]);

        return ApiResponse::success([
            'countries' => $countries,
            'currencies' => $currencies,
            'default' => [
                'country' => config('country.default.country', 'US'),
                'currency' => config('country.default.currency', 'USD'),
                'language' => config('country.default.language', 'en'),
            ],
        ]);
    }
}
