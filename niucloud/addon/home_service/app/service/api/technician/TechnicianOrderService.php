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

namespace addon\home_service\app\service\api\technician;


use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\dict\order\OrderItemType;
use addon\home_service\app\dict\order\OrderLogDict;
use addon\home_service\app\dict\order\TechnicianOrderDict;
use addon\home_service\app\model\goods\Goods;
use addon\home_service\app\model\notice\Notice;
use addon\home_service\app\model\order\Order;
use addon\home_service\app\service\core\order\CoreOrderLogService;
use addon\home_service\app\service\core\order\CoreOrderService;
use core\base\BaseApiService;
use addon\home_service\app\service\core\order\CoreOrderConfigService;
use core\exception\CommonException;
use think\facade\Db;
use addon\home_service\app\model\order\OrderItem;
use addon\home_service\app\service\core\order\SubStatusTrait;
use app\service\core\notice\NoticeService;


/**
 * 技师订单服务层
 */
class TechnicianOrderService extends BaseApiService
{

    use TechnicianTrait, SubStatusTrait;


    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
        Order::$contextRole = Order::ROLE_TECHNICIAN;
        $this->checkTechnician();
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
        }, TechnicianOrderDict::getStatus());
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
        }, TechnicianOrderDict::getTaskStatus());
        foreach ($task_status_list as &$value) {
            switch ($value['status']) {
                case TechnicianOrderDict::ABNORMAL_ORDER:
                    $value['abnormal_count'] = $TaskCount['abnormal_count'] ?? 0;
                    break;
                case TechnicianOrderDict::WAIT_SERVICE:
                    $value['timeout_count'] = $TaskCount['timeout_count'] ?? 0;
                    $value['about_to_timeout_count'] = $TaskCount['about_to_timeout_count'] ?? 0;
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
            ['technician_id', '=', $this->technician_id],
        ];
        // 超时配置与时间参数
        $config = (new CoreOrderConfigService())->getOrderConfig($this->site_id);
        $aboutToTimeoutTime = $config['order_time']['about_to_timeout_time'] ?? 30;
        $now = time();
        $timeThreshold = $now + $aboutToTimeoutTime * 60;
        // 构建包含条件判断的SQL字段
        $fields = [
            // 异常订单数（不应用refund_status过滤）
            'SUM(CASE WHEN is_abnormal = 1 THEN 1 ELSE 0 END) as abnormal_count',
            // 待服务超时订单（应用refund_status过滤）
            'SUM(CASE WHEN order_status = "' . TechnicianOrderDict::WAIT_SERVICE . '" 
                 AND reserve_service_time_stamp < ' . $now . '
                 AND refund_status = "" 
                 THEN 1 ELSE 0 END) as timeout_count',
            // 待服务即将超时订单（应用refund_status过滤）
            'SUM(CASE WHEN order_status = "' . TechnicianOrderDict::WAIT_SERVICE . '" 
                 AND reserve_service_time_stamp BETWEEN ' . $now . ' AND ' . $timeThreshold . '
                 AND refund_status = "" 
                 THEN 1 ELSE 0 END) as about_to_timeout_count',
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
        $field = 'auto_check_time,service_time,sub_status,order_id, order_type, site_id, member_id, order_no, out_trade_no, order_status,auto_refund_time, refund_status, create_time, pay_time, is_enable_refund, order_money, pay_money, is_abnormal, buy_type, reserve_service_time_stamp, taker_name, taker_mobile, taker_address, taker_full_address, member_message, technician_commission,technician_additional_commission, is_card_order, store_id, taker_longitude, taker_latitude,discount_money';
        $order = 'create_time desc';
        $search_model = $this->model->where([['site_id', '=', $this->site_id], ['pay_time', '>', 0], ['technician_id', '=', $this->technician_id]])
            ->withSearch(['order_status','order_id'], $where)->field($field)
            ->with(['item' => function ($query) {
                $query->field('order_id, item_id, item_name, item_image, pay_time,price, num, item_money, site_id, item_images,is_force_clock_in,is_force_departure,is_finish_photograph');
            }])
            ->nearby($where['lat'] ?? 0, $where['lng'] ?? 0, $where['distance'])
            ->order($order)
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
            $team['technician_sum_commission'] = bcadd($team['technician_additional_commission'], $team['technician_commission'], 2);
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
        $field = 'sub_status,order_id, member_message,site_id, reserve_service_time,member_id, order_from, order_no, out_trade_no, order_status, refund_status, ip, create_time, pay_time, close_time, auto_close_time, is_enable_refund, delete_time, order_money, pay_money,taker_name,taker_mobile,taker_province,taker_city,taker_district,taker_address,taker_full_address,taker_longitude,taker_latitude,technician_id,service_time,dispatch_time,finish_time, buy_type, reserve_service_time_stamp, technician_commission,technician_additional_commission,is_card_order,is_abnormal,take_photos,discount_money,check_photos';
        $detail = $this->model->where([['site_id', '=', $this->site_id], ['order_id', '=', $order_id], ['technician_id', '=', $this->technician_id]])->field($field)->with(['item' => function ($query) {
            $query->field('order_id, item_id, item_name,order_item_id, item_type, is_refund, item_image,price, num, item_money, site_id, out_trade_no,pay_time,is_enable_refund,item_images,refund_no,refund_status,is_force_clock_in,is_force_departure,is_finish_photograph')->append(['item_image_thumb_small', 'item_images_thumb_mid', 'item_images_thumb_small', 'item_type_name']);
        }, 'member' => function ($query) {
            $query->field('member_id, nickname, mobile, headimg');
        }, 'order_log' => function ($query) {
            $query->field('order_id, action, action_time, nick_name, action_way');
        }])->append(['order_status_info', 'time_reminder', 'order_from_name'])->findOrEmpty()->toArray();
        if (!empty($detail)){
            $detail['technician_sum_commission'] = bcadd($detail['technician_additional_commission'], $detail['technician_commission'], 2);
            $detail['reserve_service_time'] = Order::formatTime($detail['reserve_service_time_stamp']);
            $detail['take_photos'] = $detail['take_photos'] ? explode(',',$detail['take_photos']) : [];
            (new Notice())->where([['site_id', '=', $this->site_id],['order_id','=',$order_id]])->update(['unread_count'=>0]);
            $detail['check_photos'] = $detail['check_photos'] ? explode(',', $detail['check_photos']) : [];
        }
        return $detail;
    }


    /**
     * 技师出发
     * @param array $where
     * @return mixed
     */
    public function depart($data = [])
    {
        $order = (new Order())->with(['item' => function ($query) {
            $query->field('order_id, is_force_clock_in');
        }])->where([['order_id', '=', $data['order_id']]])->findOrEmpty();
        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');
        if ($order->technician_id != $this->technician_id) throw new CommonException('ORDER_NOT_EXIST');
        if ($order->order_status != TechnicianOrderDict::WAIT_SERVICE || $order->sub_status != TechnicianOrderDict::SUB_STATUS_DEPART) throw new CommonException('HOME_SERVICE_ORDER_IS_NOT_SERVICE');
        Db::startTrans();
        try {
            $orderItem = $order->item->first();
            $order->sub_status = self::getSubStatus($orderItem); // 提取为独立方法
            $order->depart_lat_lng = $data['depart_lat_lng'] ?? '';
            $order->depart_time = time();
            $order->save();
            (new CoreOrderLogService())->addLog($order->site_id, $data['order_id'], OrderLogDict::ORDER_DEPART, 'technician', $this->technician_id ?? 0, TechnicianOrderDict::getStatus(TechnicianOrderDict::WAIT_SERVICE));
            // todo 消息推送
            // (new NoticeService())->send($order->site_id, 'o2o_order_service', ['order_id' => $data['order_id']]);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage() . $e->getFile() . $e->getLine());
        }
    }


    /**
     * 技师拍照
     * @param array $where
     * @return mixed
     */
    public function photoTaken($data = [])
    {
        $order = (new Order())->with(['item' => function ($query) {
            $query->field('order_id');
        }])->where([['order_id', '=', $data['order_id']]])->findOrEmpty();
        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');
        if ($order->technician_id != $this->technician_id) throw new CommonException('ORDER_NOT_EXIST');
        if ($order->order_status != TechnicianOrderDict::WAIT_SERVICE || ($order->sub_status != TechnicianOrderDict::SUB_STATUS_PHOTO_TAKEN)) throw new CommonException('HOME_SERVICE_ORDER_IS_NOT_SERVICE');
        Db::startTrans();
        try {
            $orderItem = $order->item->first();
            $order->sub_status = self::getSubStatus($orderItem); // 提取为独立方法
            $order->take_photos = $data['take_photos'] ?? '';
            $order->take_photos_time = time();
            $order->save();
            (new CoreOrderLogService())->addLog($order->site_id, $data['order_id'], OrderLogDict::ORDER_PHOTO_TAKEN, 'technician', $this->technician_id ?? 0, TechnicianOrderDict::getStatus(TechnicianOrderDict::WAIT_SERVICE));
            // todo 消息推送
            // (new NoticeService())->send($order->site_id, 'o2o_order_service', ['order_id' => $data['order_id']]);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage() . $e->getFile() . $e->getLine());
        }
    }


    /**
     * 订单开始服务
     * @param array $data
     * @return bool
     */
    public function start(array $data)
    {
        $order = (new Order())->where([['order_id', '=', $data['order_id']]])->findOrEmpty();
        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');
        if ($order->technician_id != $this->technician_id) throw new CommonException('HOME_SERVICE_TECHNICIAN_NOT_EXIST');
        if ($order->order_status != TechnicianOrderDict::WAIT_SERVICE) throw new CommonException('HOME_SERVICE_ORDER_IS_NOT_SERVICE');
        Db::startTrans();
        try {
            $order->order_status = TechnicianOrderDict::IN_SERVICE;
            $order->is_abnormal = 0;
            $order->service_time = time();
            $order->save();
            (new CoreOrderLogService())->addLog($order->site_id, $data['order_id'], OrderLogDict::ORDER_SERVICE, 'technician', $this->technician_id ?? 0, TechnicianOrderDict::getStatus(TechnicianOrderDict::IN_SERVICE));
            // todo 消息推送
            (new NoticeService())->send($order->site_id, 'home_service_order_service', ['order_id' => $data['order_id']]);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage() . $e->getFile() . $e->getLine());
        }
    }


    /**
     * 订单 提交验证
     * @param array $data
     * @return bool
     */
    public function savecheck(array $data)
    {
        $order = (new Order())->where([['order_id', '=', $data['order_id']]])->findOrEmpty();
        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');
        if ($order->technician_id != $this->technician_id) throw new CommonException('HOME_SERVICE_TECHNICIAN_NOT_EXIST');
        if ($order->order_status != TechnicianOrderDict::IN_SERVICE) throw new CommonException('HOME_SERVICE_ORDER_IS_NOT_SERVICE');
        $no_pay_count = (new OrderItem())->where([['order_id', '=', $data['order_id']], ['pay_time', '=', 0]])->count();
        if ($no_pay_count > 0) throw new CommonException('HOME_SERVICE_ORDER_ITEM_NOT_PAY');
        Db::startTrans();
        try {
            $order->order_status = TechnicianOrderDict::WAIT_CHECK;
            $order_config = (new CoreOrderConfigService)->getOrderConfig($order->site_id)['check'];
            if ($order_config && $order_config['is_check'] == 1) {
                if ($order_config['check_length'] > 0) {
                    $order->auto_check_time = time() + $order_config['check_length'] * 86400;
                }
            }
            $order->check_photos = $data['check_photos'] ?? '';
            $order->service_finish_time = time();
            $order->save();
            (new CoreOrderLogService())->addLog($order->site_id, $data['order_id'], OrderLogDict::ORDER_SAVE_CHECK, 'technician', $this->technician_id ?? 0, TechnicianOrderDict::getStatus(TechnicianOrderDict::WAIT_CHECK));
            event('PreSettlementOrderCommission', [
                'order_id' => $order->order_id,
                'order_type' => $order->order_type,
            ]);
            // todo 消息推送
            //(new NoticeService())->send($order->site_id, 'o2o_order_service', ['order_id' => $data['order_id']]);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage());
        }

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
            'action_way' => 'technician',
            'id' => $this->technician_id,
        ]);
        return true;
    }

    /**
     * 商品服务项目
     * @param array $data
     */
    public function getGoodsItemList($data)
    {
        $order_id = $data['order_id'];
        $goods_id = (new OrderItem())->where([
            ['site_id', '=', $this->site_id],
            ['order_id', '=', $order_id],
            ['goods_id', '<>',0]
        ])->value('goods_id');

        $additional_manage =  (new Goods())->where([
            ['site_id', '=', $this->site_id],
            ['goods_id', '=', $goods_id],
        ])->value('additional_manage');

        return json_decode($additional_manage,true);
    }

    /**
     * 活动订单服务项目
     * @param array $data
     */
    public function getItemList(array $data)
    {
        $order_item_list = (new OrderItem())
            ->field('site_id,batch_id,item_name,item_image,item_money,is_service_fee,create_time,is_pay,num')
            ->withSearch(['is_pay'],$data)
            ->where([
                ['site_id', '=', $this->site_id],
                ['order_id', '=', $data['order_id']],
                ['item_type', '=', 'custom']
            ])->append(['item_image_thumb_small'])->select()->toArray();
        $result = [];
        foreach ($order_item_list as $row) {
            $batch_id = $row['batch_id'];

            // 初始化
            if (!isset($result[$batch_id])) {
                $result[$batch_id] = [
                    'item_list' => [],
                    'service_fee' => '0.00',
                    'item_money' => '0.00',
                    'item_count' => 0,
                    'is_pay' => $row['is_pay'] ? '已付款' : '待付款',
                    'create_time' => $row['create_time'],
                ];
            }

            // 金额累加
            if ($row['is_service_fee'] == 1) {
                $result[$batch_id]['service_fee'] = bcadd($result[$batch_id]['service_fee'], $row['item_money'], 2);;
            } else {
                $result[$batch_id]['item_list'][] = $row;
                $result[$batch_id]['item_count'] += $row['num'];
                $result[$batch_id]['item_money'] = bcadd($result[$batch_id]['item_money'], $row['item_money'], 2);
            }
        }
        foreach ($result as $batch_id => &$batch) {
            $batch['service_fee'] = ($batch['service_fee'] == 0 || $batch['service_fee'] === '0.00')
                ? 0
                : number_format($batch['service_fee'], 2, '.', '');

            // item_money
            $batch['item_money'] = ($batch['item_money'] == 0 || $batch['item_money'] === '0.00')
                ? 0
                : number_format($batch['item_money'], 2, '.', '');

            // total_money
            $total = bcadd($batch['service_fee'], $batch['item_money'], 2);
            $batch['total_money'] = ($total == 0 || $total === '0.00')
                ? 0
                : number_format($total, 2, '.', '');
        }
        unset($batch);
        return array_values($result);
    }

    /**
     * 添加服务项目
     */
    public function orderAddItem(array $data)
    {
        (new CoreOrderService())->orderAddItem(array_merge($data, [
            'id' => $this->technician_id,
            'action_way' => 'technician',
        ]));
        return true;
    }

    /**
     * 删除服务项目
     */
    public function orderDelItem(array $data)
    {
        (new CoreOrderService())->orderItemDel(array_merge($data, [
            'id' => $this->technician_id,
            'action_way' => 'technician',
        ]));
        return true;
    }

    /**
     * 编辑服务项目
     */
    public function orderEditItem(array $data)
    {
        (new CoreOrderService())->orderItemEdit(array_merge($data, [
            'id' => $this->technician_id,
            'action_way' => 'technician',
        ]));
        return true;
    }

//    /**
//     * 订单服务完成
//     */
//    public function orderServiceFinish(array $data)
//    {
//        (new CoreOrderService())->orderServiceFinish(array_merge($data, [
//            'id' => $this->technician_id,
//            'action_way' => 'technician',
//        ]));
//        return true;
//    }

    /**
     * 修改预约时间订单
     * @param int $order_id
     * @param array $data
     * @return \think\Response
     */
    public function editReserveServiceTime($order_id, $data)
    {
        $order = (new Order())->where([['site_id', '=', $this->site_id], ['order_id', '=', $order_id], ['technician_id', '=', $this->technician_id]])->findOrEmpty();
        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');
        if ($order->order_status != \addon\home_service\app\dict\order\OrderDict::WAIT_SERVICE) throw new CommonException('HOME_SERVICE_ORDER_IS_NOT_EDIT_SERVICE_TIME');

        if($order->is_abnormal == 1){
            if(strtotime($data['reserve_service_time']) > time()){
                $order->is_abnormal = 0;
            }
        }
        $order->reserve_service_time = $data['reserve_service_time'];
        $order->reserve_service_time_stamp = strtotime($data['reserve_service_time']);
        $order->save();
        return true;
    }
}
