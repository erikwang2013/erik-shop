<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use app\common\RefundHelper;
use PHPUnit\Framework\TestCase;

/**
 * 退款订单状态联动规则 — 全额退完置已退款(7)，部分退置退款中(6)
 */
class RefundHelperTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function full_refund_sets_order_refunded(): void
    {
        $this->assertSame(7, RefundHelper::orderStatusFor(true, 1));
        $this->assertSame(7, RefundHelper::orderStatusFor(true, 6));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function partial_refund_sets_order_refunding(): void
    {
        $this->assertSame(6, RefundHelper::orderStatusFor(false, 1));
        $this->assertSame(6, RefundHelper::orderStatusFor(false, 4));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function partial_refund_keeps_already_refunding_status(): void
    {
        $this->assertSame(6, RefundHelper::orderStatusFor(false, 6));
    }
}
