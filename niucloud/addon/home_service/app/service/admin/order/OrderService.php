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

namespace addon\home_service\app\service\admin\order;

use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\model\order\Order;
use addon\home_service\app\service\core\order\CoreOrderService;
use app\dict\common\ChannelDict;
use app\dict\pay\PayDict;
use core\base\BaseAdminService;
use think\db\Query;


/**
 *
 * Class OrderService
 */
class OrderService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
        Order::$contextRole = Order::ROLE_SYSTEM;

    }


    /**
     * 获取订单来源
     * @return array
     */
    public function getOrderFrom()
    {
        $order_from_list = ChannelDict::getType();
        $from_event_list = array_filter(event('OrderFromList')) ?? [];
        foreach ($from_event_list as $item) {
            $order_from_list = array_merge($order_from_list, $item);
        }
        return $order_from_list;
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
     * 任务订单状态及数量统计
     * @return array
     */
    public function getTaskStatus()
    {
        // 获取各状态订单数量统计
        $taskCount = $this->getPreciseTaskStatusStats();
        // 获取所有状态列表
        $taskStatusList = array_map(function ($item) {
            return [
                'name' => $item['name'],
                'status' => $item['status'],
                'count' => 0 // 初始化数量为0
            ];
        }, OrderDict::getStatus());
        // 为每个状态匹配对应的数量
        foreach ($taskStatusList as &$statusItem) {
            // 从统计结果中获取对应状态的数量
            $statusItem['count'] = $taskCount[$statusItem['status']] ?? 0;
        }
        unset($statusItem); // 解除引用
        // 计算总订单数
        $totalCount = array_sum(array_column($taskStatusList, 'count'));
        return [
            'total' => $totalCount,
            'status_list' => $taskStatusList
        ];
    }


    /**
     * 获取各状态订单的精确统计
     */
    public function getPreciseTaskStatusStats()
    {
        // 基础公共条件
        $baseWhere = [
            ['site_id', '=', $this->site_id],
            ['delete_time', '=', 0]
        ];
        // 获取所有可能的状态值
        $allStatuses = array_column(OrderDict::getStatus(), 'status');

        // 构建统计字段 - 为每个状态创建一个条件计数
        $fields = [];
        foreach ($allStatuses as $status) {
            // 判断状态值类型，字符串需要加单引号
            $statusValue = is_string($status) ? "'{$status}'" : $status;
            // 使用TP8支持的表达式语法，同时确保别名正确
            $fields[] = "SUM(CASE WHEN order_status = {$statusValue} THEN 1 ELSE 0 END) AS `{$status}`";
        }

        // 执行查询 - 使用field方法替代selectRaw
        $stats = $this->model->where($baseWhere)
            ->field(implode(', ', $fields))
            ->find();

        // 转换为整数并返回（默认为0）
        $result = [];
        foreach ($allStatuses as $status) {
            $result[$status] = intval($stats->{$status} ?? 0);
        }

        return $result;
    }


    /**
     * 订单分页列表  [
     * 'category'
     * ])
     * @param array $where
     * @return mixed
     */
    public function getPage(array $where)
    {
        $field = 'is_card_order,reserve_service_time,technician_commission,technician_additional_commission,technician_ratio,store_commission,store_additional_commission,store_ratio,   
       taker_full_address,taker_address,label_id,member_message, taker_name,taker_mobile,taker_full_address,depart_time,service_finish_time,finish_time,service_time,
      is_settlement,order_name,category_id,store_id,order_id,site_id, member_id, order_from, order_type, order_no, out_trade_no, technician_id,order_status, refund_status, ip, create_time, pay_time, close_time, auto_close_time, is_enable_refund, delete_time, order_money, pay_money';
        $order = 'create_time desc';
        $join_where = [];
        if (isset($where['member_search']) && $where['member_search'] != '') $join_where[] = ['member.member_no|member.nickname|member.username|member.mobile', 'like', "%" . $where['member_search'] . "%"];
        if (isset($where['technician_name']) && $where['technician_name'] != '') $join_where[] = ['technician.real_name', 'like', "%" . $where['technician_name'] . "%"];
        if (isset($where['store_name']) && $where['store_name'] != '') $join_where[] = ['store.store_name', 'like', "%" . $where['store_name'] . "%"];
        $search_model = $this->model->where([['order.site_id', '=', $this->site_id], ['order.delete_time', '=', 0]])
            ->where($join_where)
            ->withSearch(['label_id', 'is_settlement', 'order_no', 'order_from', 'order_status', 'member_id', 'out_trade_no', 'create_time', 'order_name', 'pay_time', 'member_search_text', 'technician_search_text'], $where)->field($field)
            ->withJoin([
                'member' => ['nickname', 'member_id'],
                'technician' => ['real_name'],
                'store' => ['store_name', 'mobile', 'store_id']
            ], 'left')
            ->with(
                [
                    'pay' => function ($query) {
                        $query->field('main_id, out_trade_no, type, pay_time, status')->append(['type_name']);
                    },
                    'goodsCategory' => function ($query) {
                        $query->field('category_name,category_id');
                    },
                    'label' => function ($query) {
                        $query->field('label_id,label_name,label_color');
                    },
                ])
            ->order($order)->append(['order_status_info', 'order_from_name', 'settlement_name', 'time_reminder']);
        $list = $this->pageQuery($search_model);
        foreach ($list['data'] as &$team) {
            $team['technician_sum_commission'] = bcadd($team['technician_additional_commission'], $team['technician_commission'], 2);
            $team['store_sum_commission'] = bcadd($team['store_additional_commission'], $team['store_commission'], 2);
            $team['taker_full_address'] = $team['taker_full_address'] . $team['taker_address'];
            if (!empty($team['pay'])) {
                $team['pay']['pay_type_name'] = PayDict::getPayType()[PayDict::FRIENDSPAY]['name'] ?? '';
            }
        }
        return $list;
    }


    /**
     * 订单分页（少量关联）列表
     * @param array $where
     * @return mixed
     */
    public function getSimplePage(array $where)
    {
        $field = 'site_id,technician_commission,technician_additional_commission,store_id,order_name,order_id,site_id,member_id, order_no, technician_id,order_status, create_time, order_money, is_card_order';
        $order = 'create_time desc';
        $member_where = [];
        if ($where['member_search'] != '') {
            $member_where = [
                ['member.member_no|member.nickname|member.username|member.mobile', 'like', "%" . $where['member_search'] . "%"],
            ];
        }
        $search_model = $this->model
            ->where([['order.site_id', '=', $this->site_id], ['technician_id', '=', $where['technician_id']]])
            ->withSearch(['order_no', 'order_name'], $where)->field($field)
            ->withJoin([
                'member' => function (Query $query) use ($member_where) {
                    $query->where($member_where)->field('member.nickname');
                }
            ], 'left')
            ->with([
                'store' => function ($query) {
                    $query->field('store_name, mobile, store_id');
                }
            ])
            ->order($order)
            ->append(['order_status_info']);
        $list = $this->pageQuery($search_model);
        foreach ($list['data'] as &$team) {
            $team['technician_sum_commission'] = bcadd($team['technician_additional_commission'], $team['technician_commission'], 2);
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
        $field = 'follow_id,check_photos,take_photos,take_photos_time,dispatch_time,reserve_service_time,technician_commission,technician_additional_commission,technician_ratio,store_commission,store_additional_commission,store_ratio,   
       label_id,member_message, taker_name,taker_mobile,taker_full_address,depart_time,service_finish_time,finish_time,service_time,
      is_settlement,order_name,category_id,store_id,order_id,site_id, member_id, order_from, order_type, order_no, out_trade_no, technician_id,order_status, refund_status, ip, create_time, pay_time, close_time, auto_close_time, is_enable_refund, delete_time, order_money, pay_money,is_card_order';
        $info = $this->model->where([['site_id', '=', $this->site_id], ['order_id', '=', $order_id]])->field($field)
            ->with([
                'item' => function ($query) {
                    // 关联获取订单项，并包含对应的支付数据
                    $query->field('order_itme_commission_ratio,store_commission,technician_commission,batch_id,is_force_departure,is_force_clock_in,order_id, item_name, item_id, item_image, price, num, item_money, site_id, item_type, item_images,refund_status, out_trade_no')
                        ->append(['item_image_thumb_small', 'item_type_name', 'item_images_thumb_mid', 'item_images_thumb_small', 'refund_status_name'])
                        ->with(['pay' => function ($payQuery) {
                            // 为Pay模型追加它的type_name和status_name属性
                            $payQuery->append(['type_name', 'status_name']);
                        }]);// 关联OrderItem模型中定义的pay关系
                },
                'member' => function ($query) {
                    $query->field('member_id, nickname, mobile, headimg');
                }
                , 'technician' => function ($query) {
                    $query->field('real_name,id,mobile,order_num');
                }
                , 'order_log' => function ($query) {
                    $query->field('order_id, action, action_time, nick_name, action_way');
                }
                , 'store' => function ($query) {
                    $query->field('store_name, mobile, store_id');
                }
                , 'goodsCategory' => function ($query) {
                    $query->field('category_name,category_id');
                },
                'evaluate' => function($query){
                    $query->append(['image_mid']);
                }
                , 'orderfollow' => function ($query) {
                    // 关联获取订单项，并包含对应的支付数据
                    $query->append(['fee_situation_name', 'result_feedback_name', 'follow_time'])
                        ->with(['sysUser']);// 关联OrderItem模型中定义的pay关系
                }
            ])
            ->append(['order_status_info', 'order_from_name', 'settlement_name', 'time_reminder'])->findOrEmpty()->toArray();
        if (!empty($info)) {
            $info['remaining_pay_money'] = bcsub($info['order_money'], $info['pay_money'], 2);
            $this->orderCoreProcess($info);
            $this->getOrderPayData($info);
            $this->separateItems($info);
            $info['refund_info'] = (new RefundService)->getLatestByOrderId($info['order_id']);
            $info['invoice_info'] = (new InvoiceService())->orderInvoiceInfo(['order_id' => $info['order_id']]);
            $this->addAction($info);
        }
        return $info;
    }


    public function addAction(&$info)
    {
        if ($info['order_status'] == OrderDict::FINISH && $info['follow_id'] == 0) {
            $info['order_status_info']['action'][] = [
                'name' => get_lang('dict_home_service_order_action.action_follow'),
                'key' => 'action_follow'
            ];
        }
    }


    /**
     * 提取订单关联的支付记录（去重）
     * @param array $info 订单详情数组（引用传递）
     */
    public function getOrderPayData(&$info)
    {
        $payList = [];
        $usedOutTradeNo = []; // 初始化去重数组（关键修正1）
        // 遍历原始item数组，提取支付记录
        if (!empty($info['item']) && is_array($info['item'])) {
            foreach ($info['item'] as &$item) { // 注意这里加&，才能修改原数组元素
                $pay = $item['pay'] ?? [];
                if (!empty($pay) && !isset($usedOutTradeNo[$pay['out_trade_no']])) {
                    $usedOutTradeNo[$pay['out_trade_no']] = true;
                    $payList[] = $pay;
                }
                // 移除当前订单项中的pay字段（关键修正2：用&引用+unset($item['pay'])）
                unset($item['pay']);
            }
            // 释放引用
            unset($item);
        }
        $info['pay_list'] = $payList;
    }


    /**
     * 拆分服务商品与附加商品
     * @param array $info 订单详情数组
     */
    private function separateItems(&$info)
    {
        // 初始化两个新数组
        $serviceItems = [];      // 服务商品（item_id ≠ 0）
        $additionalItems = [];   // 附加商品（item_id = 0）

        // 检查并处理原item数组
        if (!empty($info['item']) && is_array($info['item'])) {
            foreach ($info['item'] as $item) {
                // 根据item_id判断分类（注意：确保item中存在item_id字段）
                if (isset($item['item_id']) && $item['item_id'] != 0) {
                    $serviceItems[] = $item;
                } else {
                    $additionalItems[] = $item;
                }
            }
        }

        // 移除原item数组，添加拆分后的两个数组
        unset($info['item']);
        $info['service_items'] = $serviceItems;      // 服务商品
        $info['additional_items'] = $additionalItems; // 附加商品
    }



    /**
     * 订单核心流程（隐藏0天/0时 + 时间为空中断 + 修正节点删除）
     */
    public function orderCoreProcess(&$info)
    {

        // 1. 定义核心流程节点
        $coreProcessArray = [
            OrderDict::ORDER_CREATION => [
                'text' => get_lang('dict_home_service_order_action.action_order_creation'),
                'icon' => 'Memo',
                'time_field' => 'create_time',
            ],
            OrderDict::WAIT_DISPATCH => [
                'text' => get_lang('dict_home_service_order_action.action_dispatch'),
                'icon' => 'Sell',
                'time_field' => 'dispatch_time',
            ],
            OrderDict::SUB_STATUS_DEPART => [
                'text' => get_lang('dict_home_service_order_action.action_sub_depart'),
                'icon' => 'Bicycle',
                'time_field' => 'depart_time',
            ],
            OrderDict::SUB_STATUS_PHOTO_TAKEN => [
                'text' => get_lang('dict_home_service_order_action.action_sub_photo_taken'),
                'icon' => 'DocumentChecked',
                'time_field' => 'take_photos_time',
            ],
            OrderDict::SUB_STATUS_ACTION_START => [
                'text' => get_lang('dict_home_service_order_action.action_start'),
                'icon' => 'SuitcaseLine',
                'time_field' => 'service_time',
            ],
            OrderDict::SERVICE_FINISH => [
                'text' => get_lang('dict_home_service_order_action.action_save_check'),
                'icon' => 'FolderChecked',
                'time_field' => 'service_finish_time',
            ],
            OrderDict::ORDER_CHECK => [
                'text' => get_lang('dict_home_service_order_action.action_check'),
                'icon' => 'FolderOpened',
                'time_field' => 'finish_time',
            ],
            OrderDict::FINISH => [
                'text' => get_lang('dict_home_service_order_status.order_finish'),
                'icon' => 'Checked',
                'time_field' => 'finish_time',
            ],
        ];
        // 2. 修正节点删除逻辑（只删“出发”或“打卡”）
        $dispatch = $info['item'][0]['is_force_departure'] ?? 0;
        $photoTaken = $info['item'][0]['is_force_clock_in'] ?? 0;
        if ($dispatch == 0 && isset($coreProcessArray[OrderDict::SUB_STATUS_DEPART])) {
            unset($coreProcessArray[OrderDict::SUB_STATUS_DEPART]);
        }
        if ($photoTaken == 0 && isset($coreProcessArray[OrderDict::SUB_STATUS_PHOTO_TAKEN])) {
            unset($coreProcessArray[OrderDict::SUB_STATUS_PHOTO_TAKEN]);
        }

        // 3. 按流程顺序排序节点（确保时间差计算符合业务逻辑）
        $orderSequence = [
            OrderDict::ORDER_CREATION,
            OrderDict::WAIT_DISPATCH,
            OrderDict::SUB_STATUS_DEPART,
            OrderDict::SUB_STATUS_PHOTO_TAKEN,
            OrderDict::SUB_STATUS_ACTION_START,
            OrderDict::SERVICE_FINISH,
            OrderDict::ORDER_CHECK,
            OrderDict::FINISH,
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
        $prevKey = null;

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
            if ($prevTime !== null && $prevKey !== null) {
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
                    $timeDiff = implode(' ', array_slice($validUnits, 0, 2));

                    // 将时间差存储在前一个节点上
                    $coreProcessArray[$prevKey]['time_diff'] = $timeDiff;
                } else {
                    // 时间顺序错误（如后节点时间早于前节点）
                    $coreProcessArray[$prevKey]['time_diff'] = "";
                }
            }
            $prevTime = $currTime; // 更新前一节点时间（仅当前节点有效时）
            $prevKey = $key;      // 更新前一节点键名
        }
        unset($node); // 释放引用，避免变量污染
        // 5. 注入处理后的流程数据到订单详情
        $info['core_process'] = $coreProcessArray;
    }


    /**
     * 选择技师
     */
    public function selecttechnician($order_id = 0,$data=[])
    {


        $field = 'category_id,order_id, order_status,member_message,reserve_service_time_stamp,store_id';
        $info = $this->model->where([['site_id', '=', $this->site_id], ['order_id', '=', $order_id]])->field($field)->findOrEmpty()->toArray();

        return (new CoreOrderService())->selectTechnician([
            'reserve_service_time_stamp' => $info['reserve_service_time_stamp'],
            'action_way' => 'user',
            'store_id' => $info['store_id'],
            'category_id' => $info['category_id'],
            'site_id' => $this->site_id,
            'real_name'=>$data['real_name']??''
        ]);
    }


    /**
     * 订单派单
     */
    public function orderDispatch(array $data)
    {
        (new CoreOrderService())->orderDispatch(array_merge($data, [
            'id' => $this->uid,
            'action_way' => 'user',
        ]));
        return true;
    }


    /**
     * 订单重新派单
     */
    public function orderTransfer(array $data)
    {
        (new CoreOrderService())->orderDispatch(array_merge($data, [
            'id' => $this->uid,
            'action_way' => 'user',
        ]));
        return true;
    }


    /**
     * 订单标签
     */
    public function setLabel($data)
    {
        return (new Order())->where([['order_id', 'in', $data['order_ids'], ['site_id', '=', $this->site_id]]])->update(['label_id' => $data['label_id']]);
    }


    public function delete($order_ids)
    {
        (new CoreOrderService())->delete($order_ids, $this->site_id);
        return true;
    }


    public function orderClose($data)
    {
        (new CoreOrderService())->orderClose(array_merge($data, [
            'id' => $this->uid,
            'action_way' => 'user',
        ]));
        return true;
    }

    public function reminder($order_ids)
    {
        return (new CoreOrderService())->reminder($order_ids, $this->site_id);
    }




}
