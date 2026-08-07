<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
namespace plugin\admin\app\controller\shop;

use GuzzleHttp\Client as HttpClient;
use plugin\admin\app\controller\Crud;
use plugin\admin\app\model\shop\Refunds;
use support\exception\BusinessException;
use support\Request;

/**
 * @Apidoc\Group("refund")
 * @Apidoc\Sort(7)
 */
class ShopRefundController extends Crud
{
    protected $model = Refunds::class;

    protected function insertInput(Request $request): array
    {
        $allow = ['order_id', 'user_id', 'refund_no', 'type', 'amount', 'reason', 'images', 'status', 'reject_reason'];
        return array_intersect_key($request->post(), array_flip($allow));
    }

    protected function updateInput(Request $request): array
    {
        $primaryKey = $this->model->getKeyName();
        $id = $request->post($primaryKey);
        $allow = ['status', 'reject_reason', 'refunded_at'];
        $data = array_intersect_key($request->post(), array_flip($allow));

        // 状态语义：0待审/1通过/2驳回/3已退款
        $status = (int) ($data['status'] ?? 0);
        if ($status === 1) {
            unset($data['refunded_at']);   // 仅审核通过，尚未退款
        } elseif ($status === 3) {
            // 标记已退款前先调用 service 执行真实网关退款，失败则拒绝落库
            $this->executeRemoteRefund((int) $id);
            $data['refunded_at'] = date('Y-m-d H:i:s');
        }
        return [$id, $data];
    }

    /**
     * 调用 service 内部接口执行真实退款
     * @throws BusinessException
     */
    private function executeRemoteRefund(int $id): void
    {
        $serviceUrl = getenv('ADMIN_SERVICE_URL') ?: 'http://127.0.0.1:8787';
        $apiKey = getenv('ADMIN_API_KEY') ?: '';
        if ($apiKey === '') {
            throw new BusinessException('未配置 ADMIN_API_KEY，无法执行退款');
        }

        try {
            $response = (new HttpClient(['timeout' => 30]))->post($serviceUrl . '/api/admin/refunds/' . $id . '/execute', [
                'headers' => ['X-Admin-Key' => $apiKey, 'Content-Type' => 'application/json'],
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $msg = '无法连接退款服务';
            if ($e->getResponse()) {
                $body = json_decode((string) $e->getResponse()->getBody(), true);
                $msg = $body['msg'] ?? ('退款服务错误: ' . $e->getResponse()->getStatusCode());
            }
            throw new BusinessException($msg);
        } catch (\Throwable $e) {
            throw new BusinessException('退款服务调用失败: ' . $e->getMessage());
        }

        $body = json_decode((string) $response->getBody(), true);
        if (($body['code'] ?? 1) !== 0) {
            throw new BusinessException($body['msg'] ?? '退款执行失败');
        }
    }
}
