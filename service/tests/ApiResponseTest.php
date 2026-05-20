<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * API Response Format Test - TDD RED phase
 */
class ApiResponseTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/vendor/autoload.php';
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function success_response_has_code_zero(): void
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $response = json_decode(\app\common\ApiResponse::success($data)->rawBody(), true);

        $this->assertEquals(0, $response['code']);
        $this->assertEquals('ok', $response['msg']);
        $this->assertEquals($data, $response['data']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function fail_response_has_error_code(): void
    {
        $response = json_decode(\app\common\ApiResponse::fail('Something wrong', 422)->rawBody(), true);

        $this->assertEquals(422, $response['code']);
        $this->assertEquals('Something wrong', $response['msg']);
        $this->assertNull($response['data']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function paginate_response_has_list_and_meta(): void
    {
        $items = [['id' => 1], ['id' => 2], ['id' => 3]];
        $response = json_decode(\app\common\ApiResponse::paginate($items, 100, 1, 20)->rawBody(), true);

        $this->assertEquals(0, $response['code']);
        $this->assertArrayHasKey('list', $response['data']);
        $this->assertArrayHasKey('total', $response['data']);
        $this->assertArrayHasKey('page', $response['data']);
        $this->assertArrayHasKey('per_page', $response['data']);
        $this->assertCount(3, $response['data']['list']);
        $this->assertEquals(100, $response['data']['total']);
        $this->assertEquals(1, $response['data']['page']);
        $this->assertEquals(20, $response['data']['per_page']);
    }
}
