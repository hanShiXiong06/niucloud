<?php

namespace addon\recycle\app\listener\export;

use addon\recycle\app\model\RecycleDevice;

/**
 * 回收设备导出监听器
 */
class RecycleDeviceExportListener
{
    /**
     * 获取导出数据
     * @param array|null $param
     * @return array
     */
    public function handle($param = [])
    {
        // 确保参数是数组类型
        if (!is_array($param)) {
            $param = [];
        }
        
        $data = [];
        if (isset($param['type']) && $param['type'] == 'recycle_device') {
            $model = new RecycleDevice();
            $field = 'id, imei,imei2,sn,member_id, model, check_result, category_id, status, final_price, update_at, order_id';
            
            $where = $param['where'] ?? [];
            
            // 查询导出数据 - 使用与列表页相同的逻辑
            $search_model = $model->where([['site_id', '=', $param['site_id'] ?? 0]])
                ->withSearch(['imei', 'model', 'status', 'update_at'], $where)
                ->with(['order'])
                ->field($field)
                ->append(['status_name', 'category_name', 'nickname','code'])
                ->order('update_at desc');
            
            // 筛选分类
            if (!empty($where['category_id'])) {
                $search_model->where('category_id', $where['category_id']);
            }

            // 处理分页参数，确保是整数类型
            $page = isset($param['page']['page']) ? (int)$param['page']['page'] : 0;
            $limit = isset($param['page']['limit']) ? (int)$param['page']['limit'] : 0;

            if ($page > 0 && $limit > 0) {
                $data = $search_model->page($page, $limit)->select()->toArray();
            } else {
                $data = $search_model->select()->toArray();
            }
            
            // 处理导出数据格式
            foreach ($data as $key => $value) {
                $data[$key]['order_no'] = $value['order']['order_no'] ?? '';
                $data[$key]['create_at'] = !empty($value['update_at']) ? $value['update_at'] : '';
                
                // 将数字类型的字段强制转换为字符串，防止Excel显示为科学计数法
                // 在前面加上单引号，强制Excel识别为文本
                if (!empty($value['imei'])) {
                    $data[$key]['imei'] = "" . $value['imei'];
                }
                if (!empty($value['imei2'])) {
                    $data[$key]['imei2'] = "" . $value['imei2'];
                }
                if (!empty($value['sn'])) {
                    $data[$key]['sn'] = "" . $value['sn'];
                }
                if (!empty($value['code'])) {
                    $data[$key]['code'] = "" . $value['code'];
                }
                
                unset($data[$key]['order'], $data[$key]['id'], $data[$key]['category_id'], $data[$key]['status'], $data[$key]['order_id'], $data[$key]['update_at']);
            }
        }
        return $data;
    }
}
