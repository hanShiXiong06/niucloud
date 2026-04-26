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

namespace addon\wj_books\app\service\api\wj_books_express_log;

use addon\wj_books\app\model\wj_books_express_log\WjBooksExpressLog;
use addon\wj_books\app\model\wj_books_order\WjBooksOrder;
use core\base\BaseApiService;
use think\facade\Log;
use think\facade\Db;

/**
 * 物流回调日志API服务层
 * Class WjBooksExpressLogService
 * @package addon\wj_books\app\service\api\wj_books_express_log
 */
class WjBooksExpressLogService extends BaseApiService
{
    /**
     * @var WjBooksExpressLog
     */
    protected $model;

    /**
     * @var WjBooksOrder
     */
    protected $orderModel;

    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
        $this->model = new WjBooksExpressLog();
        $this->orderModel = new WjBooksOrder();
    }

    /**
     * 处理物流回调
     * @param array $data 回调数据
     * @return bool
     */
    public function handleCallback(array $data): bool
    {
        // 开启事务
        Db::startTrans();
        try {
            // 验证必填数据
            if (empty($data['waybill'])) {
                Log::error('物流回调数据缺少运单号');
                Db::rollback();
                return false;
            }

            // 获取site_id，优先使用URL传入的参数，其次使用当前实例的site_id，最后默认为0
            $site_id = isset($data['site_id']) ? intval($data['site_id']) : (isset($_GET['site_id']) ? intval($_GET['site_id']) : ($this->site_id ?: 0));
            
            Log::info('物流回调处理，使用site_id: ' . $site_id);

            // 构建回调日志数据
            $log = [
                'site_id' => $site_id,
                'order_id' => 0, // 默认设置为0，查到订单后再更新
                'waybill' => $data['waybill'] ?? '',
                'shopbill' => $data['shopbill'] ?? '',
                'type' => $data['type'] ?? '',
                'type_code' => $data['typeCode'] ?? null,
                'weight' => $data['weight'] ?? null,
                'real_weight' => $data['realWeight'] ?? null,
                'transfer_weight' => $data['transferWeight'] ?? null,
                'cal_weight' => $data['calWeight'] ?? null,
                'volume' => $data['volume'] ?? null,
                'parse_weight' => $data['parseWeight'] ?? null,
                'total_freight' => $data['totalFreight'] ?? null,
                'freight' => $data['freight'] ?? null,
                'freight_insured' => $data['freightInsured'] ?? null,
                'freight_haocai' => $data['freightHaocai'] ?? null,
                'change_bill' => $data['changeBill'] ?? null,
                'change_bill_freight' => $data['changeBillFreight'] ?? null,
                'fee_over' => $data['feeOver'] ?? null,
                'courier_name' => $data['courierName'] ?? null,
                'courier_phone' => $data['courierPhone'] ?? null,
                'pickup_code' => $data['pickupCode'] ?? null,
                'content' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'create_time' => date('Y-m-d H:i:s')
            ];

            // 查找对应订单
            $orderWhere = [['express_waybill', '=', $data['waybill']]];
            
            // 如果有站点ID，加入查询条件
            if ($site_id > 0) {
                $orderWhere[] = ['site_id', '=', $site_id];
            }
            
            $order = $this->orderModel->where($orderWhere)->find();

            // 存在对应订单，更新订单信息
            if (!empty($order)) {
                $log['order_id'] = $order['id'];

                // 构建订单更新数据
                $updateData = [
                    'express_status' => $data['typeCode'] ?? null,
                    'express_weight' => $data['calWeight'] ?? null,
                    'express_freight' => $data['totalFreight'] ?? null,
                    'express_courier_name' => $data['courierName'] ?? null,
                    'express_courier_phone' => $data['courierPhone'] ?? null,
                    'express_pickup_code' => $data['pickupCode'] ?? null,
                    'update_time' => date('Y-m-d H:i:s')
                ];

                // 根据物流状态更新订单状态
                $typeCode = intval($data['typeCode'] ?? 0);
                switch ($typeCode) {
                    case 1: // 待揽收
                        if ($order['status'] == 1) {
                            // 无需修改状态，维持"待上门"
                            Log::info('订单['.$order['order_no'].']物流状态更新为待揽收');
                        }
                        break;
                    
                    case 2: // 运输中
                        if ($order['status'] == 1) {
                            $updateData['status'] = 2; // 更新为"已取件"
                            $updateData['pickup_actual_time'] = date('Y-m-d H:i:s');
                            Log::info('订单['.$order['order_no'].']物流状态更新为运输中，订单状态更新为已取件');
                        }
                        break;
                    
                    case 3: // 已签收
                        if ($order['status'] == 1 || $order['status'] == 2) {
                            $updateData['status'] = 3; // 更新为"审核中"
                            $updateData['audit_progress'] = 10; // 初始化审核进度为10%
                            Log::info('订单['.$order['order_no'].']物流状态更新为已签收，订单状态更新为审核中');
                        }
                        break;
                    
                    case 4: // 拒收退回
                        // 记录拒收信息但不改变订单状态，需人工处理
                        Log::warning('订单['.$order['order_no'].']物流状态更新为拒收退回，需要人工处理');
                        break;
                    
                    case 99: // 已取消
                        if ($order['status'] < 4) { // 如果订单未完成，则标记为已取消
                            $updateData['status'] = 5; // 更新为"已取消"
                            $updateData['cancel_time'] = date('Y-m-d H:i:s');
                            $updateData['cancel_reason'] = '物流已取消：' . ($data['type'] ?? '未知原因');
                            Log::warning('订单['.$order['order_no'].']物流状态更新为已取消，订单状态更新为已取消');
                        }
                        break;
                    
                    default:
                        // 其他状态不处理
                        Log::info('订单['.$order['order_no'].']物流状态更新为未知状态：'.$typeCode);
                        break;
                }

                // 更新订单信息
                $this->orderModel->where([
                    ['id', '=', $order['id']]
                ])->update($updateData);
                
                Log::info('订单['.$order['order_no'].']物流信息已更新');
            } else {
                // 未找到对应订单，仅记录日志
                Log::warning('未找到物流运单号['.$data['waybill'].']对应的订单');
            }

            // 查询是否存在相同运单号的日志记录
            $existLog = $this->model->where([
                ['waybill', '=', $data['waybill']]
            ])->order('id', 'desc')->find();
            
            if ($existLog) {
                // 存在相同运单号的记录，执行更新操作
                Log::info('发现相同运单号['.$data['waybill'].']的日志记录，进行更新操作');
                
                // 保留原始创建时间，只更新内容
                unset($log['create_time']);
                
                // 更新日志
                $this->model->where([
                    ['id', '=', $existLog['id']]
                ])->update($log);
                
                Log::info('物流日志记录已更新，ID: '.$existLog['id']);
            } else {
                // 不存在相同运单号的记录，创建新记录
                Log::info('未发现相同运单号的日志记录，创建新记录');
                $this->model->create($log);
                Log::info('物流日志记录已创建');
            }
            
            // 提交事务
            Db::commit();
            return true;
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            Log::error('物流回调处理异常：' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return false;
        }
    }
} 