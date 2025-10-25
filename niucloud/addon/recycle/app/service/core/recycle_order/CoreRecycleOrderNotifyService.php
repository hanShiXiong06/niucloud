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

use addon\recycle\app\service\core\order\CoreOrderEventService;
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

    public function __construct()
    {
        parent::__construct();
        $this->noticeService = new NoticeService();
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

            // 获取订单详情
            $coreService = new CoreRecycleOrderService();
            $orderInfo = $coreService->getInfo($data['order_id']);
            
            if (empty($orderInfo)) {
                Log::error('【回收通知】订单不存在: ' . $data['order_id']);
                return;
            }

            // 发送通知
            $this->noticeService->send($data['site_id'], 'recycle_order_add', [
                'order_id' => $data['order_id'],
                'order_no' => $orderInfo['order_no'] ?? '',
                'customer_name' => $orderInfo['customer_name'] ?? '',
                'customer_phone' => $orderInfo['customer_phone'] ?? '',
                'device_count' => $orderInfo['device_count'] ?? 0
            ]);
            
            Log::info('【回收通知】订单创建通知发送成功: ' . $data['order_id']);
        } catch (\Exception $e) {
            Log::error('【回收通知】订单创建通知发送失败: ' . $e->getMessage(), $data);
        }
    }

    /**
     * 订单签收通知
     * @param array $data
     * @return void
     */
    public function orderSignNotify(array $data): void
    {
        try {
            Log::info('【回收通知】订单签收通知', $data);
            
            if (empty($data['order_id']) || empty($data['site_id'])) {
                Log::error('【回收通知】订单签收通知参数不完整', $data);
                return;
            }

            // 获取订单详情
            $coreService = new CoreRecycleOrderService();
            $orderInfo = $coreService->getInfo($data['order_id']);
            
            if (empty($orderInfo)) {
                Log::error('【回收通知】订单不存在: ' . $data['order_id']);
                return;
            }

            // 发送通知
            $this->noticeService->send($data['site_id'], 'recycle_order_sign', [
                'order_id' => $data['order_id'],
                'order_no' => $orderInfo['order_no'] ?? '',
                'customer_name' => $orderInfo['customer_name'] ?? '',
                'customer_phone' => $orderInfo['customer_phone'] ?? '',
                'device_count' => $orderInfo['device_count'] ?? 0
            ]);
            
            Log::info('【回收通知】订单签收通知发送成功: ' . $data['order_id']);
        } catch (\Exception $e) {
            Log::error('【回收通知】订单签收通知发送失败: ' . $e->getMessage(), $data);
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

            // 获取订单详情
            $coreService = new CoreRecycleOrderService();
            $orderInfo = $coreService->getInfo($data['order_id']);
            
            if (empty($orderInfo)) {
                Log::error('【回收通知】订单不存在: ' . $data['order_id']);
                return;
            }

            // 发送通知，包含支付模板所需的所有变量
            $this->noticeService->send($data['site_id'], 'recycle_order_pay', [
                'order_id' => $data['order_id'],
                'order_no' => $orderInfo['order_no'] ?? '',
                'customer_name' => $orderInfo['customer_name'] ?? '',
                'customer_phone' => $orderInfo['customer_phone'] ?? '',
                'pay_amount' => $this->calculateTotalAmount($orderInfo['devices'] ?? []),
                // 添加支付通知模板所需的变量
                'pay_type' => $orderInfo['pay_type'] ?? '线下支付',
                'pay_account' => $orderInfo['pay_account'] ?? '请查看订单详情',
                'pay_result' => '打款成功',
                'goods_name' => '回收设备'
            ]);
            
            Log::info('【回收通知】订单支付通知发送成功: ' . $data['order_id']);
        } catch (\Exception $e) {
            Log::error('【回收通知】订单支付通知发送失败: ' . $e->getMessage(), $data);
        }
    }

    /**
     * 订单确认通知
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

            // 获取订单详情
            $coreService = new CoreRecycleOrderService();
            $orderInfo = $coreService->getInfo($data['order_id']);
            
            if (empty($orderInfo)) {
                Log::error('【回收通知】订单不存在: ' . $data['order_id']);
                return;
            }

            // 发送通知
            $this->noticeService->send($data['site_id'], 'recycle_order_agree', [
                'order_id' => $data['order_id'],
                'order_no' => $orderInfo['order_no'] ?? '',
                'customer_name' => $orderInfo['customer_name'] ?? '',
                'customer_phone' => $orderInfo['customer_phone'] ?? '',
                'device_count' => $orderInfo['device_count'] ?? 0,
                'total_amount' => $this->calculateTotalAmount($orderInfo['devices'] ?? [])
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