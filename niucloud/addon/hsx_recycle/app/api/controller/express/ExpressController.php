<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\api\controller\express;

use addon\hsx_recycle\app\dict\express\ExpressProviderDict;
use addon\hsx_recycle\app\service\core\express\YisuExpressPushService;
use addon\hsx_recycle\app\service\core\express\RecycleExpressService;
use addon\hsx_recycle\app\service\core\order\OrderSubmitConfigService;
use core\base\BaseApiController;
use think\Response;

/**
 * API端 快递控制器
 * Class ExpressController
 * @package addon\hsx_recycle\app\api\controller\express
 */
class ExpressController extends BaseApiController
{
    /**
     * 获取快递报价
     * POST /api/hsx_recycle/express/quote
     * @return Response
     */
    public function quote(): Response
    {
        $data = $this->request->params([
            ['sender_name', ''],
            ['sender_mobile', ''],
            ['sender_province', ''],
            ['sender_city', ''],
            ['sender_district', ''],
            ['sender_address', ''],
            ['weight', 1.0],
            ['package_count', 1],
        ]);

        // 参数验证
        if (empty($data['sender_province']) || empty($data['sender_city']) || empty($data['sender_address'])) {
            return fail('请填写完整的寄件地址');
        }

        $expressService = new RecycleExpressService();

        $senderAddress = [
            'name' => $data['sender_name'],
            'mobile' => $data['sender_mobile'],
            'province' => $data['sender_province'],
            'city' => $data['sender_city'],
            'district' => $data['sender_district'],
            'address' => $data['sender_address'],
        ];

        $result = $expressService->getQuote(
            $this->request->siteId(),
            $senderAddress,
            (float)$data['weight'],
            (int)$data['package_count']
        );

        return success($result);
    }

    /**
     * 查询快递物流轨迹
     * GET /api/hsx_recycle/express/track/:order_id
     * @param int $orderId 回收订单ID
     * @return Response
     */
    public function track(int $orderId): Response
    {
        $owned = \addon\hsx_recycle\app\model\order\RecycleOrder::where('site_id', (int)$this->request->siteId())
            ->where('member_id', (int)$this->request->memberId())->where('delete_at', 0)->find($orderId);
        if (!$owned) {
            return fail('订单不存在或无权操作');
        }
        $expressService = new RecycleExpressService();

        $result = $expressService->trackOrder(
            $this->request->siteId(),
            $orderId
        );

        return success($result);
    }

    /**
     * 获取可用的快递服务商列表
     * GET /api/hsx_recycle/express/providers
     * @return Response
     */
    public function providers(): Response
    {
        $expressService = new RecycleExpressService();

        $result = $expressService->getAvailableProviders(
            $this->request->siteId()
        );

        return success($result);
    }

    /**
     * 检查平台快递是否启用
     * GET /api/hsx_recycle/express/check
     * @return Response
     */
    public function check(): Response
    {
        $expressService = new RecycleExpressService();

        $siteId = $this->request->siteId();
        $enabled = $expressService->isExpressEnabled($siteId);

        $provider = '';
        $shopAddress = null;
        if ($enabled) {
            try {
                $provider = $expressService->getActiveProvider($siteId);
            } catch (\Exception $e) {
                $provider = '';
            }
            $shopAddress = $expressService->getShopAddress($siteId);
        }

        $providerName = $provider ? ExpressProviderDict::getProviderName($provider) : '';
        $submitConfig = (new OrderSubmitConfigService())->getConfig($siteId);
        $platformDelivery = $submitConfig['platform_delivery'] ?? [];
        $displayName = trim((string)($platformDelivery['display_name'] ?? ''));
        $productName = trim((string)($platformDelivery['product_name'] ?? ''));
        $frontName = $displayName ?: ($productName ?: $providerName);

        return success(array_merge([
            'enabled' => $enabled,
            'provider' => $provider,
            'provider_name' => $providerName,
            'display_name' => $displayName,
            'product_code' => (string)($platformDelivery['product_code'] ?? ''),
            'product_name' => $productName,
            'front_name' => $frontName,
            'has_shop_address' => !empty($shopAddress),
            'prompt' => $enabled && !empty($shopAddress) && $frontName
                ? '将使用' . $frontName . '进行平台快递下单，请确认寄件地址准确。'
                : '',
        ], $expressService->pickupPolicy($siteId)));
    }

    /**
     * 取消快递单
     * POST /api/hsx_recycle/express/cancel
     * @return Response
     */
    public function cancel(): Response
    {
        $data = $this->request->params([
            ['order_id', 0],
        ]);

        if (empty($data['order_id'])) {
            return fail('缺少订单ID');
        }

        $expressService = new RecycleExpressService();
        // 此接口接收订单ID，必须校验本人归属，不能只依赖登录态。
        $owned = \addon\hsx_recycle\app\model\order\RecycleOrder::where('site_id', (int)$this->request->siteId())
            ->where('member_id', (int)$this->request->memberId())->where('delete_at', 0)->find((int)$data['order_id']);
        if (!$owned) {
            return fail('订单不存在或无权操作');
        }

        $operatorInfo = [
            'uid' => 0,
            'username' => '',
            'source' => 'user',
            'member_id' => $this->request->memberId(),
        ];

        $expressService->cancelOrder(
            $this->request->siteId(),
            (int)$data['order_id'],
            $operatorInfo
        );

        return success('取消成功');
    }

    public function kuaidi100Push(): Response
    {
        $raw = (string)$this->request->post('param', '');
        $sign = (string)$this->request->post('sign', '');
        $id = (int)$this->request->get('record_id', 0);
        try {
            if ($id <= 0 || strlen($raw) > 262144 || $raw === '') {
                throw new \RuntimeException('invalid callback');
            }
            $record = \addon\hsx_recycle\app\model\express\ExpressOrderRecord::find($id);
            $snapshot = $record ? (array)$record->api_response : [];
            $protocol = \addon\hsx_recycle\app\service\core\express\provider\Kuaidi100Protocol::class;
            if (!$record || ($snapshot['provider'] ?? '') !== 'kuaidi100'
                || !$protocol::verifyCallback($raw, $sign, (string)($snapshot['callback_salt'] ?? ''))) {
                throw new \RuntimeException('invalid signature');
            }
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $third = (string)($payload['data']['thirdOrderId'] ?? $payload['thirdOrderId'] ?? '');
            if ($third !== '' && !hash_equals($protocol::thirdOrderId((int)$record->site_id, (string)$record->third_order_no), $third)) {
                throw new \RuntimeException('order mismatch');
            }
            $taskId = trim((string)$this->request->post('taskId', ''));
            if ($taskId !== '' && !empty($snapshot['provider_task_id']) && !hash_equals((string)$snapshot['provider_task_id'], $taskId)) {
                throw new \RuntimeException('task mismatch');
            }
            $result = $protocol::normalizeCallback($payload, $taskId, $snapshot);
            (new \addon\hsx_recycle\app\service\core\express\RecyclePickupService())->applyResult($record, $result);
            return json(['result' => true, 'returnCode' => '200', 'message' => '成功']);
        } catch (\Throwable $e) {
            \think\facade\Log::warning('快递100回调未处理', ['record_id' => $id, 'reason' => $e->getMessage()]);
            return json(['result' => false, 'returnCode' => '500', 'message' => '未处理，请重试']);
        }
    }

    /**
     * 易速推送回调。必须在 2 秒内返回 SUCCESS。
     * @return Response
     */
    public function yisuPush(): Response
    {
        $requestParams = $this->request->param();
        $bodyParams = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
        $payload = $this->isListArray($bodyParams) ? $bodyParams : array_merge($requestParams, $bodyParams);

        try {
            foreach ($this->normalizeYisuPushPayloads($payload) as $payload) {
                (new YisuExpressPushService())->handle($payload);
            }
        } catch (\Throwable $e) {
            \think\facade\Log::error('易速推送处理失败：' . $e->getMessage(), ['payload' => $payload]);
        }

        return response('SUCCESS');
    }

    private function normalizeYisuPushPayloads(array $payload): array
    {
        $items = $this->isListArray($payload) ? $payload : [$payload];
        $result = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (isset($item['data']) && is_string($item['data'])) {
                $item['data'] = json_decode($item['data'], true) ?: [];
            }
            if (empty($item['orderNo']) && empty($item['waybillNo']) && empty($item['thirdOrderNo'])) {
                continue;
            }
            $result[] = $item;
        }

        return $result;
    }

    private function isListArray(array $payload): bool
    {
        if ($payload === []) {
            return false;
        }
        return array_keys($payload) === range(0, count($payload) - 1);
    }
}
