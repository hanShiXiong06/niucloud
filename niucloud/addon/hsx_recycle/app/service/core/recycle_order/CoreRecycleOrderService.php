<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * 回收订单核心服务层
 * Class CoreRecycleOrderService
 * @package addon\hsx_recycle\app\service\core\recycle_order
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

            $devicesPayload = $data['devices'] ?? [];
            if (empty($devicesPayload)) {
                $count = max(1, (int)($data['count'] ?? 1));
                $devicesPayload = array_fill(0, $count, []);
            }

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
                'device_count' => count($devicesPayload),
                'count'=>$data['count'] ,
                'remark' => $data['remark'] ?? '',
                'create_at' => time(),
                'update_at' => time(),
            ]);

            // 创建设备 判断是否有设备信息 如果没有设备信息 不创建设备记录
            if (!empty($devicesPayload) && count($devicesPayload) > 0) {
                $devices = [];
                foreach ($devicesPayload as $device) {
                    // 检查设备信息是否有效：至少需要有 imei 或 model
                    $imei = trim($device['imei'] ?? '');
                    $model = trim($device['model'] ?? '');

                    // 如果 imei 和 model 都为空，跳过该设备
                    if (empty($imei) && empty($model)) {
                        continue;
                    }

                    $categoryId = (int)($device['category_id'] ?? 1);
                    $categoryPath = $device['category_path'] ?? [];
                    if (!is_array($categoryPath) || empty($categoryPath)) {
                        $categoryPath = [ $categoryId ];
                    }

                    $devices[] = [
                        'site_id' => $data['site_id'],
                        'order_id' => $order->id,
                        'member_id' => $data['member_id'] ?? 0,
                        'category_id' => $categoryId,
                        'info' => [
                            'goods_category' => array_values(array_map('strval', $categoryPath))
                        ],
                        'imei' => $imei,
                        'model' => $model,
                        'status' => RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK,
                        'initial_price' => $device['initial_price'] ?? 0,
                        'create_at' => time(),
                        'update_at' => time(),
                    ];
                }

                // 只有在有有效设备信息时才批量保存
                if (!empty($devices)) {
                    (new RecycleDevice())->saveAll($devices);
                }
            }

            // 触发创建后事件
            CoreRecycleOrderEventService::orderCreateAfter([
                'order_id' => $order->id,
                'site_id' => $data['site_id'],
                'order_data' => $order->toArray()
            ]);

            // 触发订单下单后事件
            \addon\hsx_recycle\app\service\core\order\CoreRecycleOrderEventService::orderAddAfter([
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
