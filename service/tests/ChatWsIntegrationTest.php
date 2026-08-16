<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use app\controller\v1\ChatController;
use app\model\ChatMessages;
use app\model\ChatSessions;
use app\process\ChatWs;
use app\model\Users;
use support\Db;

// 客服密钥来自 config('admin.api_key')（config 加载时机早于测试执行），在文件加载期固定测试值
if (getenv('ADMIN_API_KEY') === false) {
    putenv('ADMIN_API_KEY=qa_test_admin_key');
}

/**
 * WS 客服补全集成测试
 * REST 全流程（建会话→发消息→close→再发被拒）+ close 校验逻辑单元覆盖
 * WS 帧路由（onMessage）依赖 Workerman 异步连接对象，单测环境不可行，核心逻辑（agentAuthValid/closeSession/sendMessage 拦截）直接覆盖
 */
class ChatWsIntegrationTest extends IntegrationTestCase
{
    private int $userId = 0;
    private int $sessionId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDb();
        $this->userId = (int) Users::create([
            'invite_code' => 'C' . substr(md5(uniqid()), 0, 8),
            'email' => 'chat_' . uniqid() . '@example.com',
            'nickname' => 'QA Chat Test',
            'status' => 1,
        ])->id;
    }

    protected function tearDown(): void
    {
        if (self::$dbAvailable) {
            Db::table('erik_chat_messages')->where('session_id', $this->sessionId)->delete();
            if ($this->sessionId) {
                Db::table('erik_chat_sessions')->where('id', $this->sessionId)->delete();
            }
            Db::table('erik_users')->where('id', $this->userId)->delete();
        }
        parent::tearDown();
    }

    private function chatRequest(string $method, string $uri, array $body = []): array
    {
        $req = $this->makeRequest($method, $uri, $body);
        $req->userId = $this->userId;
        $res = (new ChatController())->{$this->methodName($method, $uri)}($req, ...$this->args($uri));
        return [json_decode($res->rawBody(), true), $res->getStatusCode()];
    }

    private function methodName(string $method, string $uri): string
    {
        if (str_ends_with($uri, '/messages')) {
            return $method === 'GET' ? 'messages' : 'send';
        }
        if (str_ends_with($uri, '/close')) {
            return 'close';
        }
        return $method === 'GET' ? 'index' : 'store';
    }

    private function args(string $uri): array
    {
        preg_match('#/chat/sessions/(\w+)#', $uri, $m);
        return $m ? [$m[1]] : [];
    }

    public function test_rest_flow_store_send_close_then_send_rejected(): void
    {
        [$data] = $this->chatRequest('POST', '/api/chat/sessions', ['topic' => 'QA topic']);
        $this->assertSame(0, $data['code'], $data['msg'] ?? '');
        $this->sessionId = (int) $data['data']['id'];

        [$data] = $this->chatRequest('POST', "/api/chat/sessions/{$this->sessionId}/messages", ['content' => 'hello']);
        $this->assertSame(0, $data['code'], $data['msg'] ?? '');
        $this->assertSame(1, ChatMessages::where('session_id', $this->sessionId)->count());

        [$data] = $this->chatRequest('POST', "/api/chat/sessions/{$this->sessionId}/close");
        $this->assertSame(0, $data['code'], $data['msg'] ?? '');
        $session = ChatSessions::find($this->sessionId);
        $this->assertSame('closed', $session->status);
        $this->assertNotNull($session->closed_at);

        // 关闭后 REST 拒绝发送
        [$data] = $this->chatRequest('POST', "/api/chat/sessions/{$this->sessionId}/messages", ['content' => 'after close']);
        $this->assertSame(409, $data['code']);
        $this->assertSame(1, ChatMessages::where('session_id', $this->sessionId)->count());
    }

    public function test_close_is_idempotent(): void
    {
        $this->sessionId = (int) ChatSessions::create(['user_id' => $this->userId, 'topic' => '', 'status' => 'waiting'])->id;
        [$data] = $this->chatRequest('POST', "/api/chat/sessions/{$this->sessionId}/close");
        $this->assertSame(0, $data['code']);
        [$data] = $this->chatRequest('POST', "/api/chat/sessions/{$this->sessionId}/close");
        $this->assertSame(0, $data['code']);
        $this->assertSame('closed', ChatSessions::find($this->sessionId)->status);
    }

    public function test_close_send_guard_shared_with_ws_channel(): void
    {
        // WS 通道发消息也走 ChatWs::sendMessage，关闭后同样拒绝（不落库、返回 null）
        $this->sessionId = (int) ChatSessions::create(['user_id' => $this->userId, 'topic' => '', 'status' => 'waiting'])->id;
        $this->assertNotNull(ChatWs::sendMessage($this->sessionId, 'user', $this->userId, 'before'));
        $this->assertNotNull(ChatWs::closeSession($this->sessionId));
        $this->assertNull(ChatWs::sendMessage($this->sessionId, 'user', $this->userId, 'after'));
        $this->assertSame(1, ChatMessages::where('session_id', $this->sessionId)->count());
    }

    public function test_agent_auth_key_validation(): void
    {
        $this->assertTrue(ChatWs::agentAuthValid(['type' => 'auth', 'role' => 'agent', 'key' => 'qa_test_admin_key']));
        $this->assertFalse(ChatWs::agentAuthValid(['type' => 'auth', 'role' => 'agent', 'key' => 'wrong']));
        $this->assertFalse(ChatWs::agentAuthValid(['type' => 'auth', 'role' => 'agent']));
        $this->assertFalse(ChatWs::agentAuthValid(['type' => 'auth', 'role' => 'user', 'key' => 'qa_test_admin_key']));
    }

    public function test_admin_close_any_session(): void
    {
        $otherUser = (int) Users::create([
            'invite_code' => 'D' . substr(md5(uniqid()), 0, 8),
            'email' => 'chat2_' . uniqid() . '@example.com',
            'nickname' => 'QA Other',
            'status' => 1,
        ])->id;
        $otherSessionId = (int) ChatSessions::create(['user_id' => $otherUser, 'topic' => '', 'status' => 'waiting'])->id;
        $this->sessionId = $otherSessionId;

        $req = $this->makeRequest('POST', "/api/admin/chat/sessions/{$otherSessionId}/close");
        $res = (new ChatController())->adminClose($req, (string) $otherSessionId);
        $data = json_decode($res->rawBody(), true);
        $this->assertSame(0, $data['code'], $data['msg'] ?? '');
        $this->assertSame('closed', ChatSessions::find($otherSessionId)->status);

        Db::table('erik_users')->where('id', $otherUser)->delete();
    }
}
