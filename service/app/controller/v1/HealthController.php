<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;

class HealthController extends \app\controller\BaseApiController
{
    public function index(): \support\Response
    {
        $status = ['status' => 'ok', 'timestamp' => date('c')];

        try {
            \support\Db::connection()->getPdo();
            $status['db'] = 'ok';
        } catch (\Throwable $e) {
            $status['db'] = 'error';
        }

        try {
            $status['redis'] = redis()->ping() ? 'ok' : 'error';
        } catch (\Throwable $e) {
            $status['redis'] = 'error';
        }

        return ApiResponse::success($status);
    }
}
