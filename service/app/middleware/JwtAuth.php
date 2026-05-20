<?php
namespace app\middleware;

use app\common\Jwt;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class JwtAuth implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $token = $this->extractToken($request);

        if (empty($token)) {
            return json(['code' => 401, 'msg' => '请先登录', 'data' => null]);
        }

        $payload = Jwt::decode($token);
        if (!$payload) {
            return json(['code' => 401, 'msg' => 'Token无效或已过期', 'data' => null]);
        }
        $request->userId = $payload['sub'] ?? null;

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
            return $matches[1];
        }
        return $request->input('token');
    }
}
