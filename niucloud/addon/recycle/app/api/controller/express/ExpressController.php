<?php
declare(strict_types=1);

namespace addon\recycle\app\api\controller\express;

use addon\recycle\app\service\core\express\RecycleExpressService;
use core\base\BaseApiController;
use think\Response;

/**
 * API端 快递控制器
 * Class ExpressController
 * @package addon\recycle\app\api\controller\express
 */
class ExpressController extends BaseApiController
{
    /**
     * 获取快递报价
     * POST /api/recycle/express/quote
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
            $this->request->defaultSiteId(),
            $senderAddress,
            (float)$data['weight'],
            (int)$data['package_count']
        );

        return success($result);
    }

    /**
     * 查询快递物流轨迹
     * GET /api/recycle/express/track/:order_id
     * @param int $orderId 回收订单ID
     * @return Response
     */
    public function track(int $orderId): Response
    {
        $expressService = new RecycleExpressService();

        $result = $expressService->trackOrder(
            $this->request->defaultSiteId(),
            $orderId
        );

        return success($result);
    }

    /**
     * 获取可用的快递服务商列表
     * GET /api/recycle/express/providers
     * @return Response
     */
    public function providers(): Response
    {
        $expressService = new RecycleExpressService();

        $result = $expressService->getAvailableProviders(
            $this->request->defaultSiteId()
        );

        return success($result);
    }

    /**
     * 检查平台快递是否启用
     * GET /api/recycle/express/check
     * @return Response
     */
    public function check(): Response
    {
        $expressService = new RecycleExpressService();

        $siteId = $this->request->defaultSiteId();
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

        return success([
            'enabled' => $enabled,
            'provider' => $provider,  // yisu | anguo
            'has_shop_address' => !empty($shopAddress),
        ]);
    }

    /**
     * 取消快递单
     * POST /api/recycle/express/cancel
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

        $operatorInfo = [
            'uid' => 0,
            'username' => '',
            'source' => 'user',
            'member_id' => $this->request->memberId(),
        ];

        $expressService->cancelOrder(
            $this->request->defaultSiteId(),
            (int)$data['order_id'],
            $operatorInfo
        );

        return success('取消成功');
    }
}
