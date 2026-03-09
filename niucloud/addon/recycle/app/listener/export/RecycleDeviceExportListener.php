<?php

namespace addon\recycle\app\listener\export;

use addon\recycle\app\model\order\RecycleDevice;

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
            $field = 'id, imei,imei2,sn,member_id, model, check_result, category_id, status, final_price, update_at, order_id, price_uid';

            $where = $param['where'] ?? [];

            // 查询导出数据 - 使用与列表页相同的逻辑
            $search_model = $model->where([['site_id', '=', $param['site_id'] ?? 0]])
                ->withSearch(['imei', 'model', 'status', 'update_at'], $where)
                ->with([
                    'order',
                    'priceUser' => function($query) {
                        $query->field('uid,username,real_name');
                    }
                ])
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

                // 获取报价人姓名 - 兼容多种键名
                $data[$key]['quoter_name'] = '';

                // 尝试从 price_user 或 priceUser 获取
                $priceUser = $value['price_user'] ?? $value['priceUser'] ?? null;

                if (!empty($priceUser)) {
                    $data[$key]['quoter_name'] = $priceUser['real_name'] ?? $priceUser['username'] ?? '';
                }

                // 如果还是空，尝试直接从 sys_user 表查询
                if (empty($data[$key]['quoter_name']) && !empty($value['price_uid'])) {
                    try {
                        $sysUser = \app\model\sys\SysUser::where('uid', $value['price_uid'])
                            ->field('uid,username,real_name')
                            ->find();
                        if ($sysUser) {
                            $data[$key]['quoter_name'] = $sysUser['real_name'] ?? $sysUser['username'] ?? '';
                        }
                    } catch (\Exception $e) {
                        // 查询失败，保持为空
                    }
                }

                // 将数字类型的字段强制转换为字符串，防止Excel显示为科学计数法
                // 使用制表符前缀强制Excel识别为文本
                if (!empty($value['imei'])) {
                    $data[$key]['imei'] = "\t" . $value['imei'];
                }
                if (!empty($value['imei2'])) {
                    $data[$key]['imei2'] = "\t" . $value['imei2'];
                }
                if (!empty($value['sn'])) {
                    $data[$key]['sn'] = "\t" . $value['sn'];
                }
                if (!empty($value['code'])) {
                    $data[$key]['code'] = "\t" . $value['code'];
                }

                unset($data[$key]['order'], $data[$key]['price_user'], $data[$key]['priceUser'], $data[$key]['id'], $data[$key]['category_id'], $data[$key]['status'], $data[$key]['order_id'], $data[$key]['update_at'], $data[$key]['price_uid']);
            }
        }
        return $data;
    }
}
