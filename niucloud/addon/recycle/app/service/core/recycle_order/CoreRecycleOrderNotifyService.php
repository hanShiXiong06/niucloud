<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace addon\recycle\app\service\core\recycle_order;

use app\service\core\notice\NoticeService;
use core\base\BaseCoreService;
use think\facade\Log;

/**
 * 回收订单通知核心服务
 * Class CoreRecycleOrderNotifyService
 * @package addon\recycle\app\service\core\recycle_order
 */
class CoreRecycleOrderNotifyService extends BaseCoreService
{
    /**
     * @var NoticeService
     */
    protected $noticeService;

    /**
     * 小程序订单详情页路径
     */
    private const WEAPP_ORDER_DETAIL_PAGE = '/addon/recycle/pages/order/detail';

    public function __construct()
    {
        parent::__construct();
        $this->noticeService = new NoticeService();
    }

    /**
     * 获取小程序订单详情页路径
     * @param int $orderId
     * @return string
     */
    private function getWeappOrderPage(int $orderId): string
    {
        return self::WEAPP_ORDER_DETAIL_PAGE . '?id=' . $orderId;
    }

    /**
     * 订单创建通知
     * @param array $data
     * @return void
     */
    public function orderAddNotify(array $data): void
    {
        try {
            Log::info('【回收通知】订单创建通知', $data);

            if (empty($data['order_id']) || empty($data['site_id'])) {
                Log::error('【回收通知】订单创建通知参数不完整', $data);
                return;
            }

            $coreService = new CoreRecycleOrderService();
            $orderInfo = $coreService->getInfo($data['order_id']);

            if (empty($orderInfo)) {
                Log::error('【回收通知】订单不存在: ' . $data['order_id']);
                return;
            }

            // 构建收货地址
            $address = '';
            if (!empty($orderInfo['province'])) {
                $address .= $orderInfo['province'];
            }
            if (!empty($orderInfo['city'])) {
                $address .= $orderInfo['city'];
            }
            if (!empty($orderInfo['district'])) {
                $address .= $orderInfo['district'];
            }
            if (!empty($orderInfo['address'])) {
                $address .= $orderInfo['address'];
            }
            if (empty($address)) {
                $address = '待确认';
            }

            $this->noticeService->send($data['site_id'], 'recycle_order_add', [
                'order_id' => $data['order_id'],
                'member_id' => $orderInfo['member_id'] ?? 0,
                'order_no' => $orderInfo['order_no'] ?? '',
                'shop_name' => $data['shop_name'] ?? '回收中心',
                'address' => $address,
                'create_time' => $orderInfo['create_at'] ?? date('Y-m-d H:i:s'),
                '__weapp_page' => $this->getWeappOrderPage($data['order_id']),
            ]);

            Log::info('【回收通知】订单创建通知发送成功: ' . $data['order_id']);
        } catch (\Exception $e) {
            Log::error('【回收通知】订单创建通知发送失败: ' . $e->getMessage(), $data);
        }
    }

    /**
     * 订单支付通知
     * @param array $data
     * @return void
     */
    public function orderPayNotify(array $data): void
    {
        try {
            Log::info('【回收通知】订单支付通知', $data);

            if (empty($data['order_id']) || empty($data['site_id'])) {
                Log::error('【回收通知】订单支付通知参数不完整', $data);
                return;
            }

            $coreService = new CoreRecycleOrderService();
            $orderInfo = $coreService->getInfo($data['order_id']);

            if (empty($orderInfo)) {
                Log::error('【回收通知】订单不存在: ' . $data['order_id']);
                return;
            }

            $payAmount = $this->calculateTotalAmount($orderInfo['devices'] ?? []);

            $this->noticeService->send($data['site_id'], 'recycle_order_pay', [
                'order_id' => $data['order_id'],
                'member_id' => $orderInfo['member_id'] ?? 0,
                'order_no' => $orderInfo['order_no'] ?? '',
                'pay_amount' => $payAmount . '元',
                'shop_name' => $data['shop_name'] ?? '回收中心',
                'pay_time' => date('Y-m-d H:i:s'),
                '__weapp_page' => $this->getWeappOrderPage($data['order_id']),
            ]);

            Log::info('【回收通知】订单支付通知发送成功: ' . $data['order_id']);
        } catch (\Exception $e) {
            Log::error('【回收通知】订单支付通知发送失败: ' . $e->getMessage(), $data);
        }
    }

    /**
     * 订单确认通知（通知用户确认报价）
     * @param array $data
     * @return void
     */
    public function orderAgreeNotify(array $data): void
    {
        try {
            Log::info('【回收通知】订单确认通知', $data);

            if (empty($data['order_id']) || empty($data['site_id'])) {
                Log::error('【回收通知】订单确认通知参数不完整', $data);
                return;
            }

            $coreService = new CoreRecycleOrderService();
            $orderInfo = $coreService->getInfo($data['order_id']);

            if (empty($orderInfo)) {
                Log::error('【回收通知】订单不存在: ' . $data['order_id']);
                return;
            }

            $totalAmount = $this->calculateTotalAmount($orderInfo['devices'] ?? []);

            // 获取商品名称（取第一个设备的名称）
            $goodsName = '回收设备';
            if (!empty($orderInfo['devices']) && !empty($orderInfo['devices'][0]['device_name'])) {
                $goodsName = $orderInfo['devices'][0]['device_name'];
                if (count($orderInfo['devices']) > 1) {
                    $goodsName .= '等' . count($orderInfo['devices']) . '件';
                }
            }

            $this->noticeService->send($data['site_id'], 'recycle_order_agree', [
                'order_id' => $data['order_id'],
                'member_id' => $orderInfo['member_id'] ?? 0,
                'order_no' => $orderInfo['order_no'] ?? '',
                'goods_name' => $goodsName,
                'order_amount' => $totalAmount . '元',
                'create_time' => $orderInfo['create_at'] ?? date('Y-m-d H:i:s'),
                'auditor' => $data['auditor'] ?? '客服',
                '__weapp_page' => $this->getWeappOrderPage($data['order_id']),
            ]);

            Log::info('【回收通知】订单确认通知发送成功: ' . $data['order_id']);
        } catch (\Exception $e) {
            Log::error('【回收通知】订单确认通知发送失败: ' . $e->getMessage(), $data);
        }
    }

    /**
     * 计算订单总金额
     * @param array $devices
     * @return float
     */
    private function calculateTotalAmount(array $devices): float
    {
        $total = 0;
        foreach ($devices as $device) {
            $total += $device['final_price'] ?? 0;
        }
        return $total;
    }

}