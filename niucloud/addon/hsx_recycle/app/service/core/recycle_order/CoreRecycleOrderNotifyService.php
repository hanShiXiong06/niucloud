<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use app\service\core\notice\NoticeService;
use core\base\BaseCoreService;
use think\facade\Log;

/**
 * 回收订单通知核心服务
 * Class CoreRecycleOrderNotifyService
 * @package addon\hsx_recycle\app\service\core\recycle_order
 */
class CoreRecycleOrderNotifyService extends BaseCoreService
{
    /**
     * @var NoticeService
     */
    protected $noticeService;

    private CoreRecycleNoticeLogService $noticeLogService;

    /**
     * 小程序订单详情页路径
     */
    private const WEAPP_ORDER_DETAIL_PAGE = '/addon/hsx_recycle/pages/order/detail';

    public function __construct()
    {
        parent::__construct();
        $this->noticeService = new NoticeService();
        $this->noticeLogService = new CoreRecycleNoticeLogService();
    }

    /**
     * 获取小程序订单详情页路径
     * @param int $orderId
     * @return string
     */
    private function getWeappOrderPage(int $orderId, array $params = []): string
    {
        $params = array_merge(['id' => $orderId], $params);
        return self::WEAPP_ORDER_DETAIL_PAGE . '?' . http_build_query($params);
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
            $orderInfo = $coreService->getInfo((int)$data['order_id']);

            if (empty($orderInfo)) {
                Log::error('【回收通知】订单不存在: ' . $data['order_id']);
                return;
            }

            $memberId = (int)($orderInfo['member_id'] ?? 0);
            if ($memberId <= 0) {
                Log::error('【回收通知】订单创建通知 member_id 为空，跳过通知');
                return;
            }

            // 构建收货地址
            $address = '';
            if (!empty($orderInfo['province'])) $address .= $orderInfo['province'];
            if (!empty($orderInfo['city'])) $address .= $orderInfo['city'];
            if (!empty($orderInfo['district'])) $address .= $orderInfo['district'];
            if (!empty($orderInfo['address'])) $address .= $orderInfo['address'];
            if (empty($address)) $address = '待确认';

            $this->noticeService->send((int)$data['site_id'], 'recycle_order_add', [
                'order_id' => (int)$data['order_id'],
                'member_id' => $memberId,
                'order_no' => $orderInfo['order_no'] ?? '',
                'shop_name' => $data['shop_name'] ?? '回收中心',
                'address' => $address,
                'create_time' => date('Y-m-d H:i:s', (int)($orderInfo['create_at'] ?? time())),
                'status_name' => '待签收',
                'remark' => '您的回收订单已提交，请等待工作人员联系。',
                '__weapp_page' => $this->getWeappOrderPage((int)$data['order_id']),
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

            $coreService = new CoreRecycleOrderService();
            $orderInfo = $coreService->getInfo((int)$data['order_id']);

            if (empty($orderInfo)) {
                Log::error('【回收通知】订单不存在: ' . $data['order_id']);
                return;
            }

            $memberId = (int)($orderInfo['member_id'] ?? 0);
            if ($memberId <= 0) {
                Log::error('【回收通知】订单签收通知 member_id 为空，跳过通知');
                return;
            }

            $signTime = date('Y-m-d H:i:s');
            if (!empty($orderInfo['sign_at'])) {
                $rawSignAt = (int)$orderInfo['sign_at'];
                if ($rawSignAt > 0) {
                    $signTime = date('Y-m-d H:i:s', $rawSignAt);
                }
            }

            $this->noticeService->send((int)$data['site_id'], 'recycle_order_sign', [
                'order_id' => (int)$data['order_id'],
                'member_id' => $memberId,
                'order_no' => $orderInfo['order_no'] ?? '',
                'sign_time' => $signTime,
                'remark' => '您的设备已签收，正在为您质检中',
                '__weapp_page' => $this->getWeappOrderPage((int)$data['order_id']),
            ]);

            Log::info('【回收通知】订单签收通知发送成功: ' . $data['order_id']);
        } catch (\Exception $e) {
            Log::error('【回收通知】订单签收通知发送失败: ' . $e->getMessage(), $data);
        }
    }

    /**
     * 订单打款通知
     * 变量与 OrderPay listener 和模板定义保持一致：order_no, pay_type, pay_account, pay_result
     * @param array $data
     * @return void
     */
    public function orderPayNotify(array $data): void
    {
        try {
            Log::info('【回收通知】订单打款通知', $data);

            if (empty($data['order_id']) || empty($data['site_id'])) {
                Log::error('【回收通知】订单打款通知参数不完整', $data);
                return;
            }

            $coreService = new CoreRecycleOrderService();
            $orderInfo = $coreService->getInfo((int)$data['order_id']);

            if (empty($orderInfo)) {
                Log::error('【回收通知】订单不存在: ' . $data['order_id']);
                return;
            }

            $memberId = (int)($orderInfo['member_id'] ?? 0);
            if ($memberId <= 0) {
                Log::error('【回收通知】订单打款通知 member_id 为空，跳过通知');
                return;
            }

            $this->noticeService->send((int)$data['site_id'], 'recycle_order_pay', [
                'order_id' => (int)$data['order_id'],
                'member_id' => $memberId,
                'order_no' => $orderInfo['order_no'] ?? '',
                'pay_type' => $orderInfo['pay_type_name'] ?? '线下支付',
                'pay_account' => $orderInfo['pay_account'] ?? '请查看订单详情',
                'pay_result' => '打款成功',
                '__weapp_page' => $this->getWeappOrderPage((int)$data['order_id']),
            ]);

            Log::info('【回收通知】订单打款通知发送成功: ' . $data['order_id']);
        } catch (\Exception $e) {
            Log::error('【回收通知】订单打款通知发送失败: ' . $e->getMessage(), $data);
        }
    }

    /**
     * 订单完成奖励通知
     * @param array $data  需包含 order_id, site_id, reward_point
     * @return void
     */
    public function orderRewardNotify(array $data): void
    {
        try {
            Log::info('【回收通知】订单完成奖励通知', $data);

            if (empty($data['order_id']) || empty($data['site_id'])) {
                Log::error('【回收通知】订单完成奖励通知参数不完整', $data);
                return;
            }

            $coreService = new CoreRecycleOrderService();
            $orderInfo = $coreService->getInfo((int)$data['order_id']);

            if (empty($orderInfo)) {
                Log::error('【回收通知】订单不存在: ' . $data['order_id']);
                return;
            }

            $memberId = (int)($orderInfo['member_id'] ?? 0);
            if ($memberId <= 0) {
                Log::error('【回收通知】订单完成奖励通知 member_id 为空，跳过通知');
                return;
            }

            $rewardPoint = (int)($data['reward_point'] ?? 0);
            if ($rewardPoint <= 0) {
                return;
            }

            $completeTime = date('Y-m-d H:i:s');
            if (!empty($orderInfo['complete_at'])) {
                $rawAt = (int)$orderInfo['complete_at'];
                if ($rawAt > 0) {
                    $completeTime = date('Y-m-d H:i:s', $rawAt);
                }
            }

            $this->noticeService->send((int)$data['site_id'], 'recycle_order_reward', [
                'order_id'      => (int)$data['order_id'],
                'member_id'     => $memberId,
                'order_no'      => $orderInfo['order_no'] ?? '',
                'complete_time' => $completeTime,
                'reward_point'  => $rewardPoint,
                'remark'        => '恭喜您获得' . $rewardPoint . '积分奖励，可用于兑换或抵扣',
            ]);

            Log::info('【回收通知】订单完成奖励通知发送成功: ' . $data['order_id']);
        } catch (\Exception $e) {
            Log::error('【回收通知】订单完成奖励通知发送失败: ' . $e->getMessage(), $data);
        }
    }

    /**
     * 订单确认通知（通知用户确认报价）
     * 变量与 OrderAgree listener 和模板定义保持一致：order_no, time, status
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
            $orderInfo = $coreService->getInfo((int)$data['order_id']);

            if (empty($orderInfo)) {
                Log::error('【回收通知】订单不存在: ' . $data['order_id']);
                return;
            }

            $memberId = (int)($orderInfo['member_id'] ?? 0);
            if ($memberId <= 0) {
                Log::error('【回收通知】订单确认通知 member_id 为空，跳过通知');
                return;
            }

            $deviceIds = array_values(array_unique(array_filter(array_map('intval', $data['device_ids'] ?? []))));
            $scene = (string)($data['scene'] ?? ($deviceIds ? 'device_confirm' : 'order_confirm'));
            $pageParams = [];
            if ($deviceIds) {
                $pageParams = [
                    'scene' => 'device_confirm',
                    'device_ids' => implode(',', $deviceIds),
                ];
            }
            $targetPage = $this->getWeappOrderPage((int)$data['order_id'], $pageParams);
            $payload = [
                'order_id' => (int)$data['order_id'],
                'member_id' => $memberId,
                'order_no' => $orderInfo['order_no'] ?? '',
                'time' => date('Y-m-d H:i:s'),
                'status' => '待确认',
                '__weapp_page' => $targetPage,
            ];

            $sendResult = $this->noticeLogService->sendWithLog((int)$data['site_id'], 'recycle_order_agree', $payload, [
                'order_id' => (int)$data['order_id'],
                'order_no' => (string)($orderInfo['order_no'] ?? ''),
                'member_id' => $memberId,
                'scene' => $scene,
                'device_ids' => $deviceIds,
                'device_count' => count($deviceIds),
                'target_page' => $targetPage,
            ]);

            if (empty($sendResult['success'])) {
                Log::error('【回收通知】订单确认通知发送失败: ' . ($sendResult['message'] ?? ''), $data);
                return;
            }

            Log::info('【回收通知】订单确认通知发送成功: ' . $data['order_id']);
        } catch (\Exception $e) {
            Log::error('【回收通知】订单确认通知发送失败: ' . $e->getMessage(), $data);
        }
    }
}
