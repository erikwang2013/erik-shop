<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\process;

use app\common\Jwt;
use app\model\ChatMessages;
use app\model\ChatSessions;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;

/**
 * 客服实时 IM WebSocket — 客户端以 ?token=JWT&session_id=xxx 连接，仅可加入本人会话
 * 客服连接：首帧发送 {type:'auth', role:'agent', key:xxx}（key = config('admin.api_key')）
 * 通过后为 agent 角色，可回复/关闭任意会话；消息持久化与广播统一走 sendMessage()（REST 发送也复用）
 * 连接状态存静态注册表（connId => [session_id, user_id, role]），不依赖连接动态属性
 */
class ChatWs
{
    /** @var array<int, array<int, TcpConnection>> session_id => [connId => connection] */
    private static array $connections = [];

    /** @var array<int, array{session_id: int, user_id: int, role: string}> connId => 连接元数据（role: user/agent/pending） */
    private static array $meta = [];

    public function onWebSocketConnect(TcpConnection $connection, Request $request): void
    {
        $connId = spl_object_id($connection);
        $payload = Jwt::decode($request->get('token', ''));
        $userId = (int) ($payload['sub'] ?? 0);
        $sessionId = (int) $request->get('session_id', 0);
        $owned = $userId > 0 && $sessionId > 0
            && ChatSessions::where('id', $sessionId)->where('user_id', $userId)->exists();
        if ($owned) {
            self::$meta[$connId] = ['session_id' => $sessionId, 'user_id' => $userId, 'role' => 'user'];
            self::$connections[$sessionId][$connId] = $connection;
            return;
        }
        // 未持有有效 JWT 的连接保持挂起，等待首帧客服鉴权（auth 失败即关闭）
        self::$meta[$connId] = ['session_id' => 0, 'user_id' => 0, 'role' => 'pending'];
    }

    public function onMessage(TcpConnection $connection, $data): void
    {
        $connId = spl_object_id($connection);
        $meta = self::$meta[$connId] ?? null;
        if (!$meta) {
            return;
        }
        $msg = json_decode((string) $data, true);
        if (!is_array($msg)) {
            return;
        }
        if ($meta['role'] === 'pending') {
            $this->tryAgentAuth($connection, $msg);
            return;
        }
        if (($msg['type'] ?? '') === 'close') {
            $sessionId = $meta['role'] === 'agent' ? (int) ($msg['session_id'] ?? 0) : $meta['session_id'];
            if ($sessionId > 0) {
                self::closeSession($sessionId);
            }
            return;
        }
        $content = trim((string) ($msg['content'] ?? ''));
        if ($content === '') {
            return;
        }
        $sessionId = $meta['session_id'];
        if ($meta['role'] === 'agent') {
            $sessionId = (int) ($msg['session_id'] ?? 0);
            if ($sessionId <= 0) {
                return;
            }
            // 客服首次对某会话发言即加入该会话，可接收后续广播
            self::$connections[$sessionId][$connId] = $connection;
            self::$meta[$connId]['session_id'] = $sessionId;
        }
        $saved = self::sendMessage(
            $sessionId,
            $meta['role'],
            $meta['user_id'],
            $content,
            (string) ($msg['content_type'] ?? 'text'),
        );
        if (!$saved) {
            $connection->send(json_encode(['type' => 'error', 'data' => ['message' => '会话已关闭']], JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 客服凭据校验（首帧 {type:'auth', role:'agent', key:xxx}，key 与 config('admin.api_key') 恒定时间比较）
     */
    public static function agentAuthValid(array $msg): bool
    {
        $key = config('admin.api_key', '');
        if ($key === '' || ($msg['type'] ?? '') !== 'auth' || ($msg['role'] ?? '') !== 'agent') {
            return false;
        }
        $provided = $msg['key'] ?? '';
        return is_string($provided) && $provided !== '' && hash_equals($key, $provided);
    }

    private function tryAgentAuth(TcpConnection $connection, array $msg): void
    {
        $connId = spl_object_id($connection);
        $ok = self::agentAuthValid($msg);
        $connection->send(json_encode(['type' => 'auth', 'ok' => $ok, 'role' => 'agent'], JSON_UNESCAPED_UNICODE));
        if (!$ok) {
            $connection->close();
            unset(self::$meta[$connId]);
            return;
        }
        self::$meta[$connId] = ['session_id' => 0, 'user_id' => 0, 'role' => 'agent'];
    }

    public function onClose(TcpConnection $connection): void
    {
        $connId = spl_object_id($connection);
        if (!isset(self::$meta[$connId])) {
            return;
        }
        // 客服可同时加入多个会话，需从所有会话映射中摘除
        foreach (self::$connections as &$sessionConns) {
            unset($sessionConns[$connId]);
        }
        unset(self::$meta[$connId]);
    }

    /**
     * 关闭会话：更新 status=closed + closed_at，并广播 close 给该会话在线连接（幂等）
     */
    public static function closeSession(int $sessionId): ?ChatSessions
    {
        $session = ChatSessions::where('id', $sessionId)->first();
        if (!$session || $session->status === 'closed') {
            return $session;
        }
        $session->status = 'closed';
        $session->closed_at = date('Y-m-d H:i:s');
        $session->save();
        $payload = json_encode([
            'type' => 'close',
            'data' => ['session_id' => $sessionId, 'status' => 'closed', 'closed_at' => $session->closed_at],
        ], JSON_UNESCAPED_UNICODE);
        foreach (self::$connections[$sessionId] ?? [] as $conn) {
            $conn->send($payload);
        }
        return $session;
    }

    /**
     * 持久化消息并广播给会话在线成员（REST/WS 共用）；会话已关闭返回 null 不落库
     */
    public static function sendMessage(int $sessionId, string $senderType, int $senderId, string $content, string $contentType = 'text', string $platform = 'web'): ?ChatMessages
    {
        if (ChatSessions::where('id', $sessionId)->value('status') === 'closed') {
            return null;
        }
        $message = ChatMessages::create([
            'session_id' => $sessionId,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'platform' => $platform,
            'content' => $content,
            'content_type' => $contentType,
        ]);
        $payload = json_encode([
            'type' => 'message',
            'data' => [
                'id' => $message->id,
                'session_id' => $sessionId,
                'sender_type' => $senderType,
                'sender_id' => $senderId,
                'content' => $content,
                'content_type' => $contentType,
                'created_at' => $message->created_at,
            ],
        ], JSON_UNESCAPED_UNICODE);
        foreach (self::$connections[$sessionId] ?? [] as $conn) {
            $conn->send($payload);
        }
        return $message;
    }
}
