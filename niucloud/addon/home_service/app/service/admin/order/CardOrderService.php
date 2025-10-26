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

use addon\home_service\app\dict\card\CardOrderDict;
use addon\home_service\app\model\card\CardOrder;
use addon\home_service\app\service\core\card\CoreCardOrderService;
use app\dict\common\ChannelDict;
use app\dict\pay\PayDict;
use core\base\BaseAdminService;
use think\db\Query;


/**
 *
 * Class CardOrderService
 */
class CardOrderService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new CardOrder();

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
        }, CardOrderDict::getStatus());
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
        }, CardOrderDict::getStatus());
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
        $allStatuses = array_column(CardOrderDict::getStatus(), 'status');

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
        $field = 'member_message, order_id,site_id, member_id, order_from, order_type, order_no, out_trade_no,order_status, refund_status, ip, create_time, pay_time, close_time, auto_close_time, order_money, pay_money, order_name';
        $order = 'create_time desc';
        $join_where = [];
        if (isset($where['member_search']) && $where['member_search'] != '') $join_where[] = ['member.member_no|member.nickname|member.username|member.mobile', 'like', "%" . $where['member_search'] . "%"];
        $search_model = $this->model->where([['card_order.site_id', '=', $this->site_id], ['card_order.delete_time', '=', 0]])
            ->where($join_where)
            ->withSearch(['order_no', 'order_from', 'order_status', 'member_id', 'create_time', 'order_name', 'pay_time'], $where)
            ->field($field)
            ->withJoin([
                'member' => ['nickname', 'member_id'],
            ], 'left')
            ->with(
                [
                    'pay' => function ($query) {
                        $query->field('main_id, out_trade_no, type, pay_time, status')->append(['type_name']);
                    },
                ])
            ->order($order)->append(['order_status_name', 'order_from_name']);
        $list = $this->pageQuery($search_model);
        return $list;
    }


    /**
     * 订单详情
     * @param array $where
     * @return mixed
     */
    public function getDetail(int $order_id)
    {
        $field = 'member_message, order_name,order_id,site_id, member_id, order_from, order_type, order_no, out_trade_no, order_status, refund_status, ip, create_time, pay_time, close_time, auto_close_time, is_enable_refund, delete_time, order_money, pay_money';
        $info = $this->model->where([['site_id', '=', $this->site_id], ['order_id', '=', $order_id]])->field($field)
            ->with([
                'item' => function ($query) {
                    // 关联获取订单项，并包含对应的支付数据
                    $query->field('order_id,    site_id, member_id, card_id, card_sku_id, goods_id, goods_sku_id, goods_sku_name, goods_sku_image, price, num,item_money, max_use_times, sku_unit')
                        ->append(['item_image_thumb_small']);
                },
                'member' => function ($query) {
                    $query->field('member_id, nickname, mobile, headimg');
                },
                'pay' => function ($query) {
                    $query->field('main_id, out_trade_no, type, pay_time, status')->append(['type_name']);
                },
            ])
            ->append(['order_status_info', 'order_from_name'])->findOrEmpty()->toArray();
        if (!empty($info)) {
            $this->getOrderPayData($info);
        }
        return $info;
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


    public function delete($order_ids)
    {
        (new CoreCardOrderService())->delete($order_ids, $this->site_id);
        return true;
    }


    public function orderClose($card_order_id)
    {
        $data['order_id'] = $card_order_id;
        (new CoreCardOrderService())->orderClose($data);
        return true;
    }


}
