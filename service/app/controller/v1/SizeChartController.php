<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\SizeCharts;
use app\model\SizeChartValues;
use Webman\Http\Request;

class SizeChartController extends \app\controller\BaseApiController
{
    public function index(Request $request): \support\Response
    {
        $categoryId = $request->input('category_id');
        $type = $request->input('type');  // clothing/shoes

        $query = SizeCharts::query();
        if ($categoryId) $query->where('category_id', $categoryId);
        if ($type) $query->where('type', $type);

        $charts = $query->with('values')->get()->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'type' => $c->type,
            'values' => $c->values->groupBy('region')->map(fn($v) => $v->map(fn($vv) => [
                'size_label' => $vv->size_label,
                'measurement_cm' => $vv->measurement_cm,
            ])),
        ]);

        return ApiResponse::success($charts);
    }
}
