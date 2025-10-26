<?php


namespace addon\home_service\app\service\core\graborder;


use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\model\order\Order;
use core\base\BaseCoreService;
use think\facade\Db;


/**
 *  抢单大厅 订单业务
 * Class CoreOrderService
 */
class  CoreGrabOrderService extends BaseCoreService
{


    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
        Order::$contextRole = Order::ROLE_SYSTEM; //抢单业务  平台状态  师傅状态  和门店状态 是对齐的
    }

    public function __destruct()
    {
        Order::$contextRole = null;
    }


    /**
     * 分类数据  + 数据量 业务
     * Class CoreOrderService
     */
    public function grabCategory($where, $site_id = 0)
    {
        // 基础查询条件
        $condition = [
            ['o.site_id', '=', $site_id],
            ['o.pay_time', '>', 0],
            ['o.store_id', '=', 0],
            ['o.is_grab', '=', 1],
            ['o.order_status', '=', OrderDict::WAIT_DISPATCH],
        ];
        // 分类筛选条件
        if (isset($where['category_ids'])) {
            $categoryIds = is_array($where['category_ids']) ? $where['category_ids'] : explode(',', $where['category_ids']);
            $condition[] = ['o.category_id', 'in', $categoryIds];
        }
        $list = $this->model
            ->alias('o')
            ->leftJoin('home_service_goods_category gc', 'o.category_id = gc.category_id')
            ->where($condition)
            ->field(['o.category_id', 'gc.category_name', 'COUNT(o.order_id) as order_count'])
            ->group('o.category_id')
            ->select()
            ->toArray();
        $list = array_merge(
            [
                ["category_id" => "all",
                    "category_name" => "全部",
                    "order_count" => array_sum(array_column($list, 'order_count'))
                ]
            ], $list);
        return $list;
    }


    /**
     * 抢单 订单分页列表
     * @param array $where
     * @return mixed
     */
    public function getPage(array $where, $site_id = 0)
    {
        $field = 'order_id, order_no,site_id,store_id, order_status, create_time, order_money, pay_money, taker_longitude, taker_latitude, member_message, buy_type, reserve_service_time_stamp, taker_address, taker_full_address';
        $order = 'create_time desc';
        $search_model = $this->model->where([['site_id', '=', $site_id], ['pay_time', '>', 0], ['store_id', '=', 0], ['is_grab', '=', 1], ['order_status', '=', OrderDict::DISPATCH]])
            ->withSearch(['order_status', 'category_id'], $where)
            ->field($field)
            ->with(['item' => function ($query) {
                $query->field('order_id, item_name');
            }])
            ->nearby($where['lat'] ?? 0, $where['lng'] ?? 0, $where['distance'])
            ->order($order)
            ->append([]);
        $list = $this->pageQuery($search_model);
        return $list;
    }


}
