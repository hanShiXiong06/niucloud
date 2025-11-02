<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\service\api\store;


use addon\home_service\app\model\order\Order;
use addon\home_service\app\service\core\order\CoreOrderService;
use core\base\BaseApiService;
use addon\home_service\app\dict\order\StoreOrderDict;
use addon\home_service\app\dict\order\OrderDict;


/**
 * 技师订单服务层
 */
class StoreOrderService extends BaseApiService
{

    use StoreTrait;


    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
        Order::$contextRole = Order::ROLE_STORE;
        $this->checkStore();
    }

    public function __destruct()
    {
        Order::$contextRole = null;
    }


    /**
     * 订单状态
     * @return array|array[]|string
     */
    public function getStatus()
    {
        return array_map(function ($item) {
            return ['name' => $item['name'], 'status' => $item['status']];
        }, StoreOrderDict::getStatus());
    }


    /**
     * 任务订单状态
     * @return array|array[]|string
     */
    public function getTaskStatus()
    {
        $TaskCount = $this->getPreciseTaskStatusStats();
        $task_status_list = array_map(function ($item) {
            return ['name' => $item['name'], 'status' => $item['status']];
        }, StoreOrderDict::getTaskStatus());
        foreach ($task_status_list as &$value) {
            $value['action'] = [];
            switch ($value['status']) {
                case OrderDict::ABNORMAL_ORDER:
                    $value['abnormal_count'] = $TaskCount['abnormal_count'] ?? 0;
                    break;
                case StoreOrderDict::IN_PROGRESS:
                    $value['action'] = [
                        [
                            'name' => get_lang('dict_home_service_order_status.wait_service'),
                            'status' => OrderDict::WAIT_SERVICE
                        ],
                        [
                            'name' => get_lang('dict_home_service_order_status.in_service'),
                            'status' => OrderDict::IN_SERVICE
                        ],
                        [
                            'name' => get_lang('dict_home_service_order_status.wait_check'),
                            'status' => OrderDict::WAIT_CHECK
                        ],
                    ];
                    break;
            }
        }
        return $task_status_list;
    }

    /**
     * 获取精确的任务状态统计（区分退款状态过滤范围）
     */
    public function getPreciseTaskStatusStats()
    {
        // 基础公共条件
        $baseWhere = [
            ['site_id', '=', $this->site_id],
            ['pay_time', '>', 0],
            ['store_id', '=', $this->store_id],
        ];
        // 构建包含条件判断的SQL字段
        $fields = [
            // 异常订单数（不应用refund_status过滤）
            'SUM(CASE WHEN is_abnormal = 1 THEN 1 ELSE 0 END) as abnormal_count',
        ];
        // 执行查询
        $stats = $this->model->where($baseWhere)
            ->field($fields)
            ->find();
        // 转换为整数并返回
        return array_map('intval', $stats->toArray());
    }


    /**
     * 订单分页列表
     * @param array $where
     * @return mixed
     */
    public function getPage(array $where)
    {
        $field = 'auto_check_time,service_time,sub_status,order_id, order_type, site_id, member_id, order_no, out_trade_no, order_status,auto_refund_time, refund_status, create_time, pay_time, is_enable_refund, order_money, pay_money, is_abnormal, buy_type, reserve_service_time_stamp, taker_name, taker_mobile, taker_address, taker_full_address, member_message, technician_commission,technician_additional_commission, is_card_order, store_id, taker_longitude, taker_latitude,discount_money, store_additional_commission, store_commission';
        $order = 'create_time desc';
        $search_model = $this->model->where([['site_id', '=', $this->site_id], ['pay_time', '>', 0], ['store_id', '=', $this->store_id]])
            ->withSearch(['order_status', 'category_id', 'true_order_status'], $where)->field($field)
            ->with(
                [
                    'item' => function ($query) {
                        $query->field('order_id, item_name, item_image, pay_time,price, num, item_money, site_id');
                    },
                    'technician' => function ($query) {
                        $query->field('id,real_name,mobile');
                    },
                ]
            )
            ->order($order)
            ->nearby($where['lat'] ?? 0, $where['lng'] ?? 0, $where['distance'])
            ->append(['order_status_info', 'time_reminder', 'grab_type_name']);
        $list = $this->pageQuery($search_model);
        foreach ($list['data'] as &$team) {
            if (isset($where['order_status']) && $where['order_status'] == OrderDict::ABNORMAL_ORDER) {
                if (!empty($team['refund_status'])) {
                    $team['order_status_info']['name'] = '客户已申请退款';
                } else {
                    $team['order_status_info']['name'] = '超时订单';
                }
            }
            $team['store_sum_commission'] = bcadd($team['store_additional_commission'], $team['store_commission'], 2);
            $team['reserve_service_time'] = Order::formatTime($team['reserve_service_time_stamp']);
        }
        return $list;
    }


    /**
     * 订单详情
     * @param array $where
     * @return mixed
     */
    public function getDetail(int $order_id)
    {
        $field = 'sub_status,order_id, member_message,site_id, reserve_service_time,member_id, order_from, order_no, out_trade_no, order_status, refund_status, ip, create_time, pay_time, close_time, auto_close_time, is_enable_refund, delete_time, order_money, pay_money,taker_name,taker_mobile,taker_province,taker_city,taker_district,taker_address,taker_full_address,taker_longitude,taker_latitude,technician_id,service_time,dispatch_time,finish_time, buy_type, reserve_service_time_stamp, technician_commission,technician_additional_commission,is_card_order,is_abnormal,take_photos,discount_money, store_additional_commission, store_commission,check_photos';
        $detail = $this->model->where([['site_id', '=', $this->site_id], ['order_id', '=', $order_id], ['store_id', '=', $this->store_id]])->field($field)->with(
            ['item' => function ($query) {
                $query->field('order_id, item_id, item_name,order_item_id, item_type, is_refund, item_image,price, num, item_money, site_id, out_trade_no,pay_time,is_enable_refund,item_images,refund_no,refund_status')->append(['item_image_thumb_small', 'item_images_thumb_mid', 'item_images_thumb_small', 'item_type_name']);
            }, 'member' => function ($query) {
                $query->field('member_id, nickname, mobile, headimg');
            }, 'order_log' => function ($query) {
                $query->field('order_id, action, action_time, nick_name, action_way');
            }, 'technician' => function ($query) {
                $query->field('real_name,id,mobile,order_num,headimg');
            }
            ])->append(['order_status_info', 'order_from_name'])->findOrEmpty()->toArray();
        if (!empty($detail)){
            $detail['store_sum_commission'] = bcadd($detail['store_additional_commission'], $detail['store_commission'], 2);
            $detail['reserve_service_time'] = Order::formatTime($detail['reserve_service_time_stamp']);
            $detail['take_photos'] = $detail['take_photos'] ? explode(',',$detail['take_photos']) : [];
            $detail['check_photos'] = $detail['check_photos'] ? explode(',', $detail['check_photos']) : [];
        }
        return $detail;
    }


    /**
     * 技师选择
     * @param array $where
     * @return mixed
     */
    public function selectTechnician($data = [])
    {
        return (new CoreOrderService())->selectTechnician([
            'reserve_service_time_stamp' => $data['reserve_service_time_stamp'],
            'action_way' => 'store',
            'store_id' => $this->store_id,
            'site_id' => $this->site_id,
            'category_id' => $data['category_id'],
        ]);


    }

    /**
     * 门店派单
     * @param array $where
     * @return mixed
     */
    public function dispatch($data = [])
    {
        return (new CoreOrderService())->orderDispatch([
            'order_id' => $data['order_id'],
            'action_way' => 'store',
            'id' => $this->store_id,
            'technician_id' => $data['technician_id'],
        ]);
    }

    /**
     * 订单转单
     * @param array $data
     * @return bool
     */
    public function orderTransfer(array $data)
    {
        (new CoreOrderService())->orderTransfer([
            'order_id' => $data['order_id'],
            'action_way' => 'store',
            'id' => $this->store_id,
            'technician_id' => $data['technician_id'],
        ]);
        return true;
    }

    /**
     * 订单催单
     * @param int $order_id
     * @return bool
     */
    public function reminder($order_id)
    {
        $order_ids = [$order_id];
        return (new CoreOrderService())->reminder($order_ids, $this->site_id);
    }

}
