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

    /**
     * 路由参数 hashid → snowflake ID 解码
     *
     * 背景：HashidsDecode 中间件的 setParams 对 webman 控制器方法参数不生效
     * （方法参数来自 findRoute 捕获的原始 $args），所有 {id} 路由参数传入的是 hashid。
     * 控制器方法入口统一调用本方法解码；非 hashid（如 snowflake/纯数字）原样返回。
     */
    protected function decodedId(string $id): string
    {
        $decoded = \app\common\HashidsHelper::decode($id);
        return $decoded ?? $id;
    }
}
