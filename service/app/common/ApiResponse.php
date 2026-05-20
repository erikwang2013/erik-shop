<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

/**
 * 统一 API 响应格式
 *
 * 成功: {"code":0, "msg":"ok", "data":{...}}
 * 失败: {"code":1, "msg":"error", "data":null}
 * 分页: {"code":0, "msg":"ok", "data":{"list":[...], "total":100, "page":1, "per_page":20}}
 */
class ApiResponse
{
    /**
     * 成功响应
     */
    public static function success(mixed $data = null, string $msg = 'ok'): \support\Response
    {
        return json([
            'code' => 0,
            'msg' => $msg,
            'data' => $data,
        ]);
    }

    /**
     * 失败响应
     */
    public static function fail(string $msg = 'error', int $code = 1, mixed $data = null): \support\Response
    {
        return json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        ]);
    }

    /**
     * 分页响应
     */
    public static function paginate(array $items, int $total, int $page, int $perPage): \support\Response
    {
        return json([
            'code' => 0,
            'msg' => 'ok',
            'data' => [
                'list' => $items,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }
}
