<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

/**
 * 业务异常：预期内的业务拒绝（库存不足/券不可用等），调用方映射为 HTTP 422。
 * 与数据库/系统异常区分，只有本异常的 message 可以安全返回给客户端。
 */
class BusinessException extends \RuntimeException
{
}
