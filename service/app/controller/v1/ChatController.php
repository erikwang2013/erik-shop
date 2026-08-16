<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\model\ChatMessages;
use app\model\ChatSessions;
use app\process\ChatWs;
use Webman\Http\Request;

/**
 * 客服会话 API（REST 侧；实时推送走 /ws/chat WebSocket 进程）
 */
class ChatController extends \app\controller\BaseApiController
{
    /**
     * 创建会话
     * POST /api/chat/sessions  {topic?}
     */
    public function store(Request $request): \support\Response
    {
        $session = ChatSessions::create([
            'user_id' => $request->userId,
            'topic' => trim((string) $request->input('topic', '')),
            'status' => 'waiting',
        ]);
        return ApiResponse::success([
            'id' => $session->id,
            'status' => $session->status,
        ], '会话创建成功');
    }

    /**
     * 我的会话列表
     * GET /api/chat/sessions
     */
    public function index(Request $request): \support\Response
    {
        $list = ChatSessions::where('user_id', $request->userId)
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'topic' => $s->topic,
                'agent_id' => $s->agent_id,
                'status' => $s->status,
                'created_at' => $s->created_at,
            ]);
        return ApiResponse::success(['list' => $list]);
    }

    /**
     * 会话消息列表
     * GET /api/chat/sessions/{id}/messages
     */
    public function messages(Request $request, string $id): \support\Response
    {
        $session = $this->ownedSession($request->userId, $id);
        if (!$session) {
            return ApiResponse::fail('会话不存在', 404);
        }
        $list = ChatMessages::where('session_id', $session->id)
            ->orderBy('id', 'asc')
            ->limit(200)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'sender_type' => $m->sender_type,
                'sender_id' => $m->sender_id,
                'content' => $m->content,
                'content_type' => $m->content_type,
                'created_at' => $m->created_at,
            ]);
        return ApiResponse::success(['list' => $list]);
    }

    /**
     * 发送消息
     * POST /api/chat/sessions/{id}/messages  {content, content_type?}
     */
    public function send(Request $request, string $id): \support\Response
    {
        $session = $this->ownedSession($request->userId, $id);
        if (!$session) {
            return ApiResponse::fail('会话不存在', 404);
        }
        $content = trim((string) $request->input('content', ''));
        if ($content === '') {
            return ApiResponse::fail('消息内容不能为空', 422);
        }
        $message = ChatWs::sendMessage(
            $session->id,
            'user',
            (int) $request->userId,
            $content,
            (string) $request->input('content_type', 'text'),
            (string) ($request->platform ?? 'web'),
        );
        return ApiResponse::success([
            'id' => $message->id,
            'created_at' => $message->created_at,
        ], '发送成功');
    }

    private function ownedSession(int $userId, string $id): ?ChatSessions
    {
        return ChatSessions::where('id', $this->decodedId($id))->where('user_id', $userId)->first();
    }
}
