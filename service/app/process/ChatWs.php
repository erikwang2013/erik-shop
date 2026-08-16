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
 * 消息持久化与广播统一走 sendMessage()（REST 发送消息也复用，保证双通道一致）
 * 连接状态存静态注册表（connId => [session_id, user_id]），不依赖连接动态属性
 */
class ChatWs
{
    /** @var array<int, array<int, TcpConnection>> session_id => [connId => connection] */
    private static array $connections = [];

    /** @var array<int, array{session_id: int, user_id: int}> connId => 连接元数据 */
    private static array $meta = [];

    public function onWebSocketConnect(TcpConnection $connection, Request $request): void
    {
        $payload = Jwt::decode($request->get('token', ''));
        $userId = (int) ($payload['sub'] ?? 0);
        $sessionId = (int) $request->get('session_id', 0);
        $owned = $userId > 0 && $sessionId > 0
            && ChatSessions::where('id', $sessionId)->where('user_id', $userId)->exists();
        if (!$owned) {
            $connection->close();
            return;
        }
        $connId = spl_object_id($connection);
        self::$meta[$connId] = ['session_id' => $sessionId, 'user_id' => $userId];
        self::$connections[$sessionId][$connId] = $connection;
    }

    public function onMessage(TcpConnection $connection, $data): void
    {
        $meta = self::$meta[spl_object_id($connection)] ?? null;
        if (!$meta) {
            return;
        }
        $msg = json_decode((string) $data, true);
        $content = trim((string) ($msg['content'] ?? ''));
        if ($content === '') {
            return;
        }
        self::sendMessage(
            $meta['session_id'],
            'user',
            $meta['user_id'],
            $content,
            (string) ($msg['content_type'] ?? 'text'),
        );
    }

    public function onClose(TcpConnection $connection): void
    {
        $connId = spl_object_id($connection);
        $meta = self::$meta[$connId] ?? null;
        if ($meta) {
            unset(self::$connections[$meta['session_id']][$connId], self::$meta[$connId]);
        }
    }

    /**
     * 持久化消息并广播给会话在线成员（REST/WS 共用）
     */
    public static function sendMessage(int $sessionId, string $senderType, int $senderId, string $content, string $contentType = 'text', string $platform = 'web'): ?ChatMessages
    {
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
