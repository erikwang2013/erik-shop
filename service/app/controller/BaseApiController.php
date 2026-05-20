<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller;

use app\common\ApiResponse;

/**
 * API 控制器基类
 * 提供统一响应方法和请求辅助方法
 */
class BaseApiController
{
    /**
     * 成功响应
     */
    protected function success(mixed $data = null, string $msg = 'ok'): \support\Response
    {
        return ApiResponse::success($data, $msg);
    }

    /**
     * 失败响应
     */
    protected function fail(string $msg = 'error', int $code = 1): \support\Response
    {
        return ApiResponse::fail($msg, $code);
    }

    /**
     * 分页响应
     */
    protected function paginate(array $items, int $total, int $page, int $perPage): \support\Response
    {
        return ApiResponse::paginate($items, $total, $page, $perPage);
    }
}
