<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

/**
 * 分布式锁等待超时：调用方应映射为 HTTP 429（操作太快）。
 * 继承 RuntimeException，未适配的旧调用方按原逻辑捕获不受影响。
 */
class LockTimeoutException extends \RuntimeException
{
}
