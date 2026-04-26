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

namespace addon\wj_books\app\service\admin\wj_books_express_log;

use addon\wj_books\app\model\wj_books_express_log\WjBooksExpressLog;
use addon\wj_books\app\model\wj_books_order\WjBooksOrder;
use core\base\BaseAdminService;
use think\db\exception\DbException;
use think\facade\Log;
use think\facade\Db;

/**
 * 物流回调日志服务层
 * Class WjBooksExpressLogService
 * @package addon\wj_books\app\service\admin\wj_books_express_log
 */
class WjBooksExpressLogService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new WjBooksExpressLog();
    }
    
    /**
     * 获取物流回调日志列表
     * @param array $params
     * @return array
     */
    public function getList(array $params = []): array
    {
        $field = 'id, site_id, order_id, waybill, shopbill, type, type_code, weight, cal_weight, total_freight, freight, courier_name, courier_phone, create_time';
        $order = 'create_time desc';
        
        $where = [['site_id', '=', $this->site_id]];
        
        // 构建搜索条件
        if (!empty($params['waybill'])) {
            $where[] = ['waybill', 'like', '%' . $params['waybill'] . '%'];
        }
        
        if (!empty($params['shopbill'])) {
            $where[] = ['shopbill', 'like', '%' . $params['shopbill'] . '%'];
        }
        
        if (!empty($params['order_id'])) {
            $where[] = ['order_id', '=', $params['order_id']];
        }
        
        if (isset($params['type_code']) && $params['type_code'] !== '') {
            $where[] = ['type_code', '=', $params['type_code']];
        }
        
        // 处理时间范围查询
        if (!empty($params['start_time']) && !empty($params['end_time'])) {
            $where[] = ['create_time', 'between', [$params['start_time'], $params['end_time']]];
        } else if (!empty($params['start_time'])) {
            $where[] = ['create_time', '>=', $params['start_time']];
        } else if (!empty($params['end_time'])) {
            $where[] = ['create_time', '<=', $params['end_time']];
        }
        
        $search_model = $this->model->where($where)
            ->field($field)
            ->order($order);
            
        $list = $this->pageQuery($search_model);
        
        return $list;
    }

    /**
     * 获取物流回调日志详情
     * @param int $id
     * @return array
     */
    public function getInfo(int $id): array
    {
        $field = '*';
        $info = $this->model->field($field)->where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $id]
        ])->findOrEmpty()->toArray();
        
        return $info;
    }

    /**
     * 删除物流回调日志
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $model = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $id]
        ])->find();
        
        if (empty($model)) {
            return false;
        }
        
        $res = $model->delete();
        return $res;
    }

    /**
     * 清空物流回调日志
     * @return bool
     */
    public function clear(): bool
    {
        $this->model->where([
            ['site_id', '=', $this->site_id]
        ])->delete();
        
        return true;
    }

    /**
     * 处理物流回调
     * @param array $data
     * @return bool
     */
    public function handleCallback(array $data): bool
    {
        try {
            // 记录回调日志
            $log = [
                'site_id' => $this->site_id,
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
            $orderModel = new WjBooksOrder();
            $order = $orderModel->where([
                ['site_id', '=', $this->site_id],
                ['express_waybill', '=', $data['waybill']]
            ])->find();

            if (!empty($order)) {
                $log['order_id'] = $order['id'];

                // 更新订单物流状态
                $updateData = [
                    'express_status' => $data['typeCode'] ?? null,
                    'express_weight' => $data['calWeight'] ?? null,
                    'express_freight' => $data['totalFreight'] ?? null,
                    'express_courier_name' => $data['courierName'] ?? null,
                    'express_courier_phone' => $data['courierPhone'] ?? null,
                    'express_pickup_code' => $data['pickupCode'] ?? null,
                    'update_time' => date('Y-m-d H:i:s')
                ];

                // 如果物流状态为已签收，则更新订单状态为审核中
                if (($data['typeCode'] ?? 0) == 3 && $order['status'] == 2) {
                    $updateData['status'] = 3;
                }

                $orderModel->where([
                    ['id', '=', $order['id']]
                ])->update($updateData);
            }

            // 保存日志
            $this->model->create($log);
            return true;
        } catch (\Exception $e) {
            Log::error('物流回调处理异常：' . $e->getMessage());
            return false;
        }
    }
} 