<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core\recycle_order;

use addon\recycle\app\model\RecycleOrder;
use addon\recycle\app\model\RecycleDevice;
use addon\recycle\app\dict\order\RecycleOrderDict;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * 回收订单核心服务层
 * Class CoreRecycleOrderService
 * @package addon\recycle\app\service\core\recycle_order
 */
class CoreRecycleOrderService extends BaseCoreService
{
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleOrder();
    }

    /**
     * 创建订单
     * @param array $data
     * @return RecycleOrder
     * @throws CommonException
     */
    public function create(array $data)
    {
        try {
            Db::startTrans();

            // 创建订单
            $order = RecycleOrder::create([
                'site_id' => $data['site_id'],
                'member_id' => $data['member_id'] ?? 0,
                'order_no' => $this->createOrderNo(),
                'customer_name' => $data['customer_name'] ?? '',
                'customer_phone' => $data['customer_phone'] ?? '',
                'delivery_type' => $data['delivery_type'] ?? RecycleOrderDict::DELIVERY_TYPE_EXPRESS,
                'express_company' => $data['express_company'] ?? '',
                'express_no' => $data['express_no'] ?? '',
                'status' => RecycleOrderDict::ORDER_STATUS_PENDING_SIGN,
                'device_count' => count($data['devices'] ?? []),
                'remark' => $data['remark'] ?? '',
                'create_at' => time(),
                'update_at' => time(),
            ]);

            // 创建设备
            if (!empty($data['devices'])) {
                $devices = [];
                foreach ($data['devices'] as $device) {
                    $devices[] = [
                        'site_id' => $data['site_id'],
                        'order_id' => $order->id,
                        'member_id' => $data['member_id'] ?? 0,
                        'category_id' => $device['category_id'] ?? 1,
                        'imei' => $device['imei'] ?? '',
                        'model' => $device['model'] ?? '',
                        'status' => RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK,
                        'initial_price' => $device['initial_price'] ?? 0,
                        'create_at' => time(),
                        'update_at' => time(),
                    ];
                }
                (new RecycleDevice())->saveAll($devices);
            }

            // 触发创建后事件
            CoreRecycleOrderEventService::orderCreateAfter([
                'order_id' => $order->id,
                'site_id' => $data['site_id'],
                'order_data' => $order->toArray()
            ]);

            // 触发订单下单后事件
            \addon\recycle\app\service\core\order\CoreRecycleOrderEventService::orderAddAfter([
                'order_id' => $order->id,
                'site_id' => $data['site_id'],
                'member_id' => $data['member_id'] ?? 0
            ]);

            Db::commit();
            return $order;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('创建回收订单失败：' . $e->getMessage());
            throw new CommonException('创建订单失败：' . $e->getMessage());
        }
    }

    /**
     * 获取订单详情
     * @param int $order_id
     * @return array
     */
      public function getInfo(int $order_id)
    {
        try {
            Log::record('【回收通知】CoreRecycleOrderService->getInfo 尝试获取订单: ' . $order_id, 'notice');
            
            // 移除了有问题的payment关联
            $info = $this->model->where([['id', '=', $order_id]])->withoutField('delete_time')->findOrEmpty()->toArray();
            
            if (empty($info)) {
                Log::record('【回收通知】CoreRecycleOrderService->getInfo 订单不存在: ' . $order_id, 'error');
                return [];
            }
            
            // 获取相关设备信息
            $info['devices'] = (new RecycleDevice())->where([['order_id', '=', $order_id]])->select()->toArray();
            
            Log::record('【回收通知】CoreRecycleOrderService->getInfo 成功获取订单: ' . json_encode($info), 'notice');
            return $info;
        } catch (\Exception $e) {
            Log::record('【回收通知】CoreRecycleOrderService->getInfo 异常: ' . $e->getMessage(), 'error');
            return [];
        }
    }


    /**
     * 生成订单编号
     * @return string
     */
    protected function createOrderNo()
    {
        return 'R' . date('YmdHis') . mt_rand(1000, 9999);
    }
}
