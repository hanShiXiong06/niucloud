<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core\recycle_order;

use addon\recycle\app\service\core\delivery\Anguo;
use addon\recycle\app\model\order\RecycleOrder;
use think\facade\Log;
use think\facade\Db;
use core\exception\CommonException;

/**
 * 回收订单 - 安果ERP快递服务
 * Class RecycleAnguoDeliveryService
 */
class RecycleAnguoDeliveryService
{
    protected $anguoApi;
    protected $siteId;

    public function __construct(int $siteId = 0)
    {
        $this->siteId = $siteId;
        // 获取安果ERP配置
        $config = $this->getAnguoConfig();
        $this->anguoApi = new Anguo($config);
    }

    /**
     * 获取安果ERP配置
     * @return array
     */
    private function getAnguoConfig(): array
    {
        // TODO: 从系统配置表读取安果ERP的配置
        // 暂时使用默认配置
        return [
            'base_url' => 'http://115.190.35.168:3000',
            'express_company_id' => 2, // 顺丰
            'timeout' => 30,
        ];
    }

    /**
     * 获取可用的预约时间
     * @return array
     */
    public function getAvailablePickupTimes(): array
    {
       
        try {
            // 调用安果API获取预约时间
            $pickupTime = $this->anguoApi->getSendStartTime();
            
            return [
                'pickup_time' => $pickupTime['sendStartTime'],
                'tips' => $pickupTime['tips']
            ];
        } catch (\Exception $e) {
            Log::error('取预约时间失败：' . $e->getMessage());
            throw new CommonException('获取预约时间失败：' . $e->getMessage());
        }
    }

    /**
     * 创建快递订单（收件人付费模式）
     * @param int $orderId 回收订单ID
     * @param array $senderAddress 寄件人地址信息
     * @param string $pickupTime 预约时间
     * @param float $weight 重量（kg）
     * @return array
     */
    public function createDeliveryOrder(int $orderId, array $senderAddress, string $pickupTime, float $weight = 1.0): array
    {
        Db::startTrans();
        try {
            // 1. 获取回收订单信息
            $order = RecycleOrder::find($orderId);
            if (!$order) {
                throw new CommonException('回收订单不存在');
            }

            // 检查是否已下单
            if (!empty($order->express_no)) {
                throw new CommonException('该订单已下单，运单号：' . $order->express_no);
            }

            // 2. 获取商户收货地址（收件人）
            $shopAddress = $this->getShopAddress($order->site_id);
            if (!$shopAddress) {
                throw new CommonException('未配置商户收货地址，请先在后台配置');
            }

            // 3. 组装安果API参数
            $params = [
                // 寄件人（用户）
                'senderName' => $senderAddress['name'],
                'senderMobile' => $senderAddress['mobile'],
                'senderProvince' => $senderAddress['province'],
                'senderCity' => $senderAddress['city'],
                'senderDistrict' => $senderAddress['district'],
                'senderAddress' => $senderAddress['address'],

                // 收件人（商户）
                'receiveName' => $shopAddress['contact_name'],
                'receiveMobile' => $shopAddress['mobile'],
                'receiveProvince' => $shopAddress['province'],
                'receiveCity' => $shopAddress['city'],
                'receiveDistrict' => $shopAddress['district'],
                'receiveAddress' => $shopAddress['address'],

                // 订单信息
                'weight' => $weight,
                'remark' => "回收订单：{$order->order_no}",
                'deliveryType' => 'SF',
            ];

            // 4. 调用安果API下单
            Log::write("===回收订单{$orderId}开始调用安果ERP下单===");
            $result = $this->anguoApi->sendOrderByRecipient($params);

            // 5. 检查结果
            if (isset($result['type']) && $result['type'] === 'error') {
                throw new CommonException($result['msg']);
            }

            if (empty($result['orderNo'])) {
                throw new CommonException('安果ERP下单成功但未返回运单号');
            }

            // 6. 更新回收订单信息
            $order->save([
                'express_no' => $result['orderNo'],
                'delivery_platform' => 'anguo',
                'delivery_status' => 1, // 已下单
                'pickup_time' => $pickupTime,
                'delivery_data' => json_encode([
                    'sender' => $senderAddress,
                    'receiver' => $shopAddress,
                    'weight' => $weight,
                    'create_time' => date('Y-m-d H:i:s'),
                    'tracking_number' => $result['orderNo']
                ], JSON_UNESCAPED_UNICODE),
                'update_at' => time()
            ]);

            Log::write("===回收订单{$orderId}安果ERP下单成功，运单号：{$result['orderNo']}===");

            Db::commit();

            return [
                'order_id' => $orderId,
                'tracking_number' => $result['orderNo'],
                'delivery_id' => $result['deliveryId']
            ];

        } catch (\Exception $e) {
            Db::rollback();
            Log::error("回收订单{$orderId}安果ERP下单失败：" . $e->getMessage());
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 获取商户收货地址
     * @param int $siteId
     * @return array|null
     */
    private function getShopAddress(int $siteId): ?array
    {
        // 从recycle_shop_address表获取默认收货地址
        $address = Db::name('recycle_shop_address')
            ->where('site_id', $siteId)
            ->where('is_default_refund', 1)
            ->find();

        if (!$address) {
            // 如果没有设置默认地址，获取第一个地址
            $address = Db::name('recycle_shop_address')
                ->where('site_id', $siteId)
                ->order('id', 'asc')
                ->find();
        }

        if (!$address) {
            return null;
        }

        // 解析省市区（通过full_address解析）
        // 河北省石家庄市新华区太和电子城6A37
        $fullAddress = $address['full_address'];
        $addressParts = $this->parseAddress($fullAddress);

        return [
            'contact_name' => $address['contact_name'],
            'mobile' => $address['mobile'],
            'province' => $addressParts['province'],
            'city' => $addressParts['city'],
            'district' => $addressParts['district'],
            'address' => $addressParts['detail'],
            'full_address' => $fullAddress
        ];
    }

    /**
     * 解析详细地址为省市区
     * @param string $fullAddress 完整地址，如：河北省石家庄市新华区太和电子城6A37
     * @return array
     */
    private function parseAddress(string $fullAddress): array
    {
        // 简单正则解析省市区
        $pattern = '/^(.+?省)(.+?市)(.+?[区县])(.+)$/u';

        if (preg_match($pattern, $fullAddress, $matches)) {
            return [
                'province' => $matches[1],
                'city' => $matches[2],
                'district' => $matches[3],
                'detail' => $matches[4]
            ];
        }

        // 如果无法解析，返回原地址
        return [
            'province' => '',
            'city' => '',
            'district' => '',
            'detail' => $fullAddress
        ];
    }

    /**
     * 取消快递订单
     * @param int $orderId
     * @return bool
     */
    public function cancelDelivery(int $orderId): bool
    {
        try {
            $order = RecycleOrder::find($orderId);
            if (!$order || empty($order->express_no)) {
                throw new CommonException('订单不存在或未下单');
            }

            if ($order->delivery_platform !== 'anguo') {
                throw new CommonException('该订单不是通过安果ERP下单，无法取消');
            }

            // 调用安果API取消订单
            $result = $this->anguoApi->cancelOrder([
                'tracking_number' => $order->express_no
            ]);

            if ($result['code'] == 200) {
                // 更新状态
                $order->save([
                    'delivery_status' => 4, // 已取消
                    'update_at' => time()
                ]);

                Log::write("===回收订单{$orderId}快递已取消===");
                return true;
            }

            throw new CommonException($result['msg'] ?? '取消失败');

        } catch (\Exception $e) {
            Log::error("取消快递订单失败：" . $e->getMessage());
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 更新快递状态（手动同步）
     * @param int $orderId
     * @return array
     */
    public function syncDeliveryStatus(int $orderId): array
    {
        try {
            $order = RecycleOrder::find($orderId);
            if (!$order || empty($order->express_no)) {
                throw new CommonException('订单不存在或未下单');
            }

            // 查询运单详情
            $detail = $this->anguoApi->getOrderDetail($order->express_no);

            if (isset($detail['type']) && $detail['type'] === 'error') {
                throw new CommonException($detail['msg']);
            }

            // 更新快递状态
            $newStatus = $this->mapAnguoStatus($detail['status'] ?? 0);
            if ($newStatus > 0) {
                $order->save([
                    'delivery_status' => $newStatus,
                    'update_at' => time()
                ]);
            }

            return $detail;

        } catch (\Exception $e) {
            Log::error("同步快递状态失败：" . $e->getMessage());
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 映射安果状态到系统状态
     * @param int $anguoStatus 安果状态：1-待取件 2-运输中 3-已签收 4-已取消 5-异常
     * @return int 系统状态：0-未下单，1-已下单，2-运输中，3-已签收，4-已取消
     */
    private function mapAnguoStatus(int $anguoStatus): int
    {
        $statusMap = [
            1 => 1, // 待取件 -> 已下单
            2 => 2, // 运输中 -> 运输中
            3 => 3, // 已签收 -> 已签收
            4 => 4, // 已取消 -> 已取消
            5 => 2, // 异常 -> 运输中（保持运输中状态）
        ];

        return $statusMap[$anguoStatus] ?? 0;
    }
}
