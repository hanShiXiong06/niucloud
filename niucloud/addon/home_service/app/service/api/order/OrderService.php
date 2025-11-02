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

namespace addon\home_service\app\service\api\order;

use addon\home_service\app\dict\order\MemberOrderDict as OrderDict;
use addon\home_service\app\dict\order\OrderDict as HomeServiceOrderDict;
use addon\home_service\app\dict\order\OrderItemType;
use addon\home_service\app\dict\order\OrderLogDict;
use addon\home_service\app\dict\order\RefundDict;
use addon\home_service\app\model\order\Order;
use addon\home_service\app\model\order\OrderRefund;
use addon\home_service\app\service\core\order\CoreOrderLogService;
use addon\home_service\app\service\core\order\CoreOrderService;
use app\model\member\Member;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 订单服务层
 * Class OrderService
 * @package app\service\api
 */
class OrderService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
        Order::$contextRole = Order::ROLE_MEMBER;
    }

    /**
     * 订单状态
     * @return array|array[]|string
     */
    public function getStatus()
    {
        return array_map(function ($item) {
            return ['name' => $item['name'], 'status' => $item['status']];
        }, OrderDict::getStatus());
    }


    /**
     * 订单分页列表
     * @param array $where
     * @return mixed
     */
    public function getPage(array $where)
    {
        $field = 'order_id, order_type, site_id, member_id, order_from, order_type, order_no, out_trade_no, order_status, refund_status, ip, create_time, pay_time, close_time, auto_close_time, is_enable_refund, delete_time, order_money, pay_money, taker_longitude, taker_latitude, check_photos,technician_id,take_photos,take_photos_time,reserve_service_time_stamp,is_evaluate,sub_status,is_card_order';
        $order = 'create_time desc';
        $search_model = $this->model->where([['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id]])
            ->withSearch(['order_status', 'order_id'], $where)->field($field)
            ->with(
                [
                    'item' => function ($query) {
                        $query->field('order_id, item_id, item_name, item_image, pay_time,price, num, item_money,order_item_id, site_id, item_images, is_force_clock_in, is_force_departure, is_finish_photograph, item_type,is_pay,batch_id,create_time,is_service_fee')->with(['goods_sku'])->append(['item_image_thumb_small', 'item_image_thumb_mid']);;
                    },
                    'technician' => function ($query) {
                        $query->field('real_name,id,mobile,order_num,headimg');
                    }
                ])
            ->order($order)
            ->append(['order_status_info']);
        $list = $this->pageQuery($search_model);
        foreach ($list['data'] as $k => $v) {
            $list['data'][$k]['total_money'] = number_format(array_sum(array_column($v['item'], 'item_money')), 2, '.', '');
            $item = [];
            foreach ($v['item'] as $key => $val) {
                if ($val['pay_time'] != 0) {
                    $item[] = $val;
                }
                //增项服务
                $add_item_list = [];
                if ($val['item_type'] == OrderItemType::CUSTOM) {
                    $add_item_list[] = $val;
                    unset($list['data'][$k]['item'][$key]);
                }

                $list['data'][$k]['add_item_list'] = [];
                if (!empty($add_item_list)) {
                    $result = [];
                    foreach ($add_item_list as $row) {
                        $batch_id = $row['batch_id'];

                        // 初始化
                        if (!isset($result[$batch_id])) {
                            $result[$batch_id] = [
                                'item_list' => [],
                                'service_fee' => '0.00',
                                'item_money' => '0.00',
                                'item_count' => 0,
                                'is_pay' => $row['is_pay'] ? '已付款' : '待付款',
                                'is_pay_status' => $row['is_pay'],
                                'create_time' => $row['create_time'],
                            ];
                        }

                        // 金额累加
                        if ($row['is_service_fee'] == 1) {
                            $result[$batch_id]['service_fee'] += bcadd($result[$batch_id]['service_fee'], $row['item_money'], 2);;
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
                        $list['data'][$k]['wait_pay_batch_id'] = 0;
                        if ($batch['is_pay_status'] == 0) {
                            $list['data'][$k]['wait_pay_batch_id'] = $batch_id;
                        }
                    }

                    $list['data'][$k]['add_item_list'] = array_values($result);
                    unset($batch);

                }
            }
            $list['data'][$k]['reserve_service_time'] = $this->model->formatTime($v['reserve_service_time_stamp']);
            $list['data'][$k]['total_pay_money'] = number_format(array_sum(array_column($item, 'item_money')), 2, '.', '');


        }
        return $list;
    }

    /**
     * 订单详情
     * @param array $where
     * @return mixed
     */
    public function getDetail($order_id)
    {
        $field = 'service_finish_time,take_photos_time,store_id,order_id, check_code,site_id, reserve_service_time,member_message,order_type, member_id, order_from, order_type, order_no, out_trade_no, order_status, refund_status, ip, create_time, pay_time, close_time, auto_close_time, is_enable_refund, delete_time, order_money, pay_money, taker_name,taker_mobile,taker_province,taker_city,taker_district,taker_address,taker_full_address,taker_longitude,taker_latitude,technician_id,service_time,dispatch_time,finish_time,is_evaluate,sub_status,take_photos,take_photos_time,depart_time,check_photos,is_card_order';
        $info = $this->model->where([['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id], ['order_id|out_trade_no', '=', $order_id]])->field($field)
            ->with(['item' => function ($query) {
                $query->field('order_id, item_name, sku_name,goods_id,item_id,order_item_id, item_type, is_refund, item_image,price, num, item_money, site_id, out_trade_no,pay_time,is_enable_refund, refund_status, refund_no, item_images, is_force_clock_in, is_force_departure, is_finish_photograph, batch_id, is_pay, create_time, is_service_fee, discount_money')->append(['item_image_thumb_small', 'item_image_thumb_mid', 'item_type_name', 'item_images_thumb_mid', 'item_images_thumb_small']);
            }, 'pay' => function ($query) {
                $query->field('main_id, out_trade_no, type, pay_time, status')->append(['type_name']);
            }, 'order_log' => function ($query) {
                $query->field('order_id, action, action_time, nick_name, action_way');
            }, 'technician' => function ($query) {
                $query->field('real_name,id,mobile,order_num,headimg');
            }])->append(['order_status_info'])
            ->findOrEmpty()->toArray();
        if (!empty($info)) {
            $info['total_money'] = number_format(array_sum(array_column($info['item'], 'item_money')), 2, '.', '');
            $item = [];
            foreach ($info['item'] as $val) {
                if ($val['pay_time'] != 0) {
                    $item[] = $val;
                }
            }
            $info['total_pay_money'] = number_format(array_sum(array_column($item, 'item_money')), 2, '.', '');
            if (!empty($info['pay'])) {
                if ($info['member_id'] != $info['pay']['main_id']) {
                    $member_info = (new Member())->field('nickname,headimg')->where([['site_id', '=', $this->site_id], ['member_id', '=', $info['pay']['main_id']]])->findOrEmpty()->toArray();
                    if (!empty($member_info)) {
                        $info['pay']['pay_member'] = $member_info['nickname'];
                        $info['pay']['pay_member_headimg'] = $member_info['headimg'];
                    }
                }
            }

            //增项服务
            $add_item_list = [];
            foreach ($info['item'] as $key => $value) {
                if ($value['item_type'] == OrderItemType::CUSTOM) {
                    $add_item_list[] = $value;
                    unset($info['item'][$key]);
                }
            }

            $info['add_item_list'] = [];
            if (!empty($add_item_list)) {
                $result = [];
                foreach ($add_item_list as $row) {
                    $batch_id = $row['batch_id'];

                    // 初始化
                    if (!isset($result[$batch_id])) {
                        $result[$batch_id] = [
                            'item_list' => [],
                            'service_fee' => '0.00',
                            'item_money' => '0.00',
                            'item_count' => 0,
                            'is_pay' => $row['is_pay'] ? '已付款' : '待付款',
                            'is_pay_status' => $row['is_pay'],
                            'create_time' => $row['create_time'],
                        ];
                    }

                    // 金额累加
                    if ($row['is_service_fee'] == 1) {
                        $result[$batch_id]['service_fee'] += bcadd($result[$batch_id]['service_fee'], $row['item_money'], 2);;
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
                    $info['wait_pay_batch_id'] = 0;
                    if ($batch['is_pay_status'] == 0) {
                        $info['wait_pay_batch_id'] = $batch_id;
                    }
                }
                $info['add_item_list'] = array_values($result);
                unset($batch);
            }

            $this->orderCoreProcess($info);

            if (!empty($info['check_photos'])) {
                $info['check_photos'] = explode(',', $info['check_photos']);
            }

            //获取退款信息
            $info['refund'] = (new OrderRefund())->where([['order_id', '=', $info['order_id']], ['status', 'in', [RefundDict::WAIT_REFUND,RefundDict::REFUND_COMPLETED]]])->findOrEmpty();
        }
        return $info;
    }


    /**
     * 订单验收
     * @param int $order_id
     * @return \think\Response
     */
    public function check($data)
    {
        $order = (new Order())->where([['order_id', '=', $data['order_id']]])->findOrEmpty();
        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');
        if ($order->order_status != OrderDict::WAIT_CHECK) throw new CommonException('HOME_SERVICE_ORDER_IS_NOT_SERVICE');
        try {
            $data['id'] = $this->member_id;
            $data['action_way'] = 'member';
            (new CoreOrderService())->orderServiceFinish($data);
            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage());
        }

    }

    /**
     * 会员取消订单
     * @param int $order_id
     * @return \think\Response
     */
    public function cancel(int $order_id)
    {
        $order = $this->model->where([['order_id', '=', $order_id], ['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id]])->findOrEmpty();
        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');
        if ($order['order_status'] != OrderDict::WAIT_PAY) throw new CommonException('ORDER_NOT_ALLOW_CLOSE');

        try {
            (new CoreOrderService())->close($order);
            CoreOrderLogService::addLog($order['site_id'], $order_id, OrderLogDict::ORDER_CANCEL, 'member', $this->member_id, OrderDict::getStatus(OrderDict::CLOSE));
            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage());
        }
    }
    //
    //    /**
    //     * 删除订单
    //     * @param int $order_id
    //     * @return true
    //     */
    //    public function delete(int $order_id)
    //    {
    //        $order = $this->model->where([['order_id', '=', $order_id], ['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id]])->findOrEmpty();
    //        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');
    //        if ($order['order_status'] != OrderDict::CLOSE) throw new CommonException('ORDER_NOT_ALLOW_DELETE');
    //        $order->delete_time = time();
    //        $order->save();
    //        return true;
    //    }
    //
    //
    //    /**
    //     * 获取订单数量
    //     * @return array
    //     * @throws \think\db\exception\DbException
    //     */
    //    public function num()
    //    {
    //        $data['wait_pay'] = $this->model->where([
    //                ['site_id', '=', $this->site_id],
    //                ['member_id', '=', $this->member_id],
    //                ['order_status', '=', OrderDict::WAIT_PAY],
    //            ])->count() ?? 0;
    //
    //        $data['wait_service'] = $this->model->where([
    //                ['site_id', '=', $this->site_id],
    //                ['member_id', '=', $this->member_id],
    //                ['order_status', '=', OrderDict::WAIT_SERVICE],
    //            ])->count() ?? 0;
    //
    //        $data['in_service'] = $this->model->where([
    //                ['site_id', '=', $this->site_id],
    //                ['member_id', '=', $this->member_id],
    //                ['order_status', '=', OrderDict::IN_SERVICE],
    //            ])->count() ?? 0;
    //
    //        $data['finish'] = $this->model->where([
    //                ['site_id', '=', $this->site_id],
    //                ['member_id', '=', $this->member_id],
    //                ['order_status', '=', OrderDict::FINISH],
    //                ['is_evaluate', '=', 0],
    //            ])->count() ?? 0;
    //
    //        $data['refund'] = (new OrderRefund())->where([
    //                ['site_id', '=', $this->site_id],
    //                ['member_id', '=', $this->member_id],
    //                ['status', 'in', [
    //                    RefundDict::WAIT_REFUND,
    //                    RefundDict::REFUNDING,
    //                    RefundDict::REFUND_REFUSE
    //                ]]
    //            ])->count() ?? 0;
    //
    //        return $data;
    //    }

    /**
     * 订单验收
     * @param int $order_id
     * @param array $data
     * @return \think\Response
     */
    public function editReserveServiceTime($order_id, $data)
    {
        $order = (new Order())->where([['order_id', '=', $order_id]])->findOrEmpty();
        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');
        if ($order->order_status != \addon\home_service\app\dict\order\OrderDict::WAIT_DISPATCH) throw new CommonException('HOME_SERVICE_ORDER_IS_NOT_EDIT_SERVICE_TIME');

        $order->reserve_service_time = $data['reserve_service_time'];
        $order->reserve_service_time_stamp = strtotime($data['reserve_service_time']);
        $order->save();
    }

    /**
     * 订单核心流程（隐藏0天/0时 + 时间为空中断 + 修正节点删除）
     */
    public function orderCoreProcess(&$info)
    {
        // 1. 定义核心流程节点
        $coreProcessArray = [
            HomeServiceOrderDict::SUB_STATUS_DEPART => [
                'text' => get_lang('dict_home_service_order_action.action_sub_depart'),
                'icon' => 'Bicycle',
                'time_field' => 'depart_time',
            ],
            HomeServiceOrderDict::SUB_STATUS_PHOTO_TAKEN => [
                'text' => get_lang('dict_home_service_order_action.action_sub_photo_taken'),
                'icon' => 'DocumentChecked',
                'time_field' => 'take_photos_time',
            ],
            HomeServiceOrderDict::SUB_STATUS_ACTION_START => [
                'text' => get_lang('dict_home_service_order_action.action_start'),
                'icon' => 'SuitcaseLine',
                'time_field' => 'service_time',
            ],
            HomeServiceOrderDict::SERVICE_FINISH => [
                'text' => get_lang('dict_home_service_order_action.action_save_check'),
                'icon' => 'FolderChecked',
                'time_field' => 'service_finish_time',
            ],
            HomeServiceOrderDict::FINISH => [
                'text' => get_lang('dict_home_service_order_status.order_finish'),
                'icon' => 'Checked',
                'time_field' => 'finish_time',
            ],
        ];
        // 2. 修正节点删除逻辑（只删“出发”或“打卡”）
        $dispatch = $info['item'][0]['is_force_departure'] ?? 0;
        $photoTaken = $info['item'][0]['is_force_clock_in'] ?? 0;
        if ($dispatch == 0 && isset($coreProcessArray[HomeServiceOrderDict::SUB_STATUS_DEPART])) {
            unset($coreProcessArray[HomeServiceOrderDict::SUB_STATUS_DEPART]);
        }
        if ($photoTaken == 0 && isset($coreProcessArray[HomeServiceOrderDict::SUB_STATUS_PHOTO_TAKEN])) {
            unset($coreProcessArray[HomeServiceOrderDict::SUB_STATUS_PHOTO_TAKEN]);
        }

        // 3. 按流程顺序排序节点（确保时间差计算符合业务逻辑）
        $orderSequence = [
            HomeServiceOrderDict::ORDER_CREATION,
            HomeServiceOrderDict::WAIT_DISPATCH,
            HomeServiceOrderDict::SUB_STATUS_DEPART,
            HomeServiceOrderDict::SUB_STATUS_PHOTO_TAKEN,
            HomeServiceOrderDict::SUB_STATUS_ACTION_START,
            HomeServiceOrderDict::SERVICE_FINISH,
            HomeServiceOrderDict::ORDER_CHECK,
            HomeServiceOrderDict::FINISH,
        ];
        $sortedProcess = [];
        foreach ($orderSequence as $key) {
            if (isset($coreProcessArray[$key])) {
                $sortedProcess[$key] = $coreProcessArray[$key];
            }
        }
        $coreProcessArray = $sortedProcess;
        // 4. 计算时间差（核心优化：隐藏0天/0时，不显示秒）
        $prevTime = null;
        foreach ($coreProcessArray as $key => &$node) {
            $timeField = $node['time_field'];
            $currTime = null;

            // 4.1 统一转换为有效时间戳（排除0、null、空字符串、无效时间）
            if (isset($info[$timeField])) {
                $fieldValue = $info[$timeField];
                if ((is_numeric($fieldValue) && $fieldValue > 0) || (is_string($fieldValue) && !empty($fieldValue))) {
                    $currTime = is_numeric($fieldValue) ? (int)$fieldValue : strtotime($fieldValue);
                    $currTime = $currTime === false ? null : $currTime; // 过滤无效时间（如"abc"）
                }
            }

            $node['time_value'] = $currTime;
            $node['time_diff'] = '';

            // 4.2 时间为空 → 中断循环（后续节点未执行，无需处理）
            if ($currTime === null) {
                $node['time_diff'] = "";
                break;
            }

            // 4.3 计算时间差（不足1分钟按1分钟算，隐藏0单位）
            if ($prevTime !== null) {
                if ($currTime >= $prevTime) {
                    $diff = $currTime - $prevTime;
                    // 拆分时间单位（天、时、分，无秒）
                    $days = floor($diff / (24 * 3600));
                    $remainder = $diff % (24 * 3600);
                    $hours = floor($remainder / 3600);
                    $remainder %= 3600;
                    $minutes = floor($remainder / 60);

                    // 核心规则：不足1分钟按1分钟算
                    $seconds = $remainder % 60;
                    if ($minutes == 0 && $seconds > 0) {
                        $minutes = 1;
                    }

                    // 收集非零的时间单位（隐藏0天、0时、0分）
                    $validUnits = [];
                    if ($days > 0) $validUnits[] = "{$days}天";
                    if ($hours > 0) $validUnits[] = "{$hours}时";
                    if ($minutes > 0) $validUnits[] = "{$minutes}分";

                    // 处理极端情况（理论不会出现，防止异常）
                    if (empty($validUnits)) {
                        $validUnits[] = "1分"; // 确保至少有一个有效单位
                    }

                    // 取前2个有效单位（保证信息简洁，避免冗余）
                    $node['time_diff'] = implode(' ', array_slice($validUnits, 0, 2));
                } else {
                    $node['time_diff'] = ""; // 时间顺序错误（如后节点时间早于前节点）
                }
            } else {
                $node['time_diff'] = ""; // 第一个节点无前置时间，无需计算差
            }

            $prevTime = $currTime; // 更新前一节点时间（仅当前节点有效时）
        }
        unset($node); // 释放引用，避免变量污染
        // 5. 注入处理后的流程数据到订单详情
        $info['core_process'] = $coreProcessArray;
    }


    public function __destruct()
    {
        Order::$contextRole = null;
    }
}
