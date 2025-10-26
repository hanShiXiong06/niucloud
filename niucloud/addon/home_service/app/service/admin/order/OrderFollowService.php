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

use addon\home_service\app\model\order\Order;
use addon\home_service\app\model\order\OrderFollow;
use addon\home_service\app\dict\order\OrderDict;
use core\base\BaseAdminService;
use core\exception\AdminException;
use think\facade\Db;

/**
 * 订单会访
 * Class OrderService
 */
class OrderFollowService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new OrderFollow();
    }


    /**
     * 获取各状态订单的精确统计
     */
    public function getTaskStatus()
    {
        $stats = (new Order)->where([
            ['site_id', '=', $this->site_id],
            ['order_status', '=', OrderDict::FINISH],
        ])->fieldRaw(
            'COUNT(*) as total,
        SUM(CASE WHEN follow_id > 0 THEN 1 ELSE 0 END) as visited,
        SUM(CASE WHEN follow_id = 0 THEN 1 ELSE 0 END) as unvisited'
        )->find()->toArray();
        return [
            'total' => $stats['total']??0,
            'status_list' => [
                ['status' => 'unvisited', 'name' => '待回访', 'count' => $stats['unvisited']??0],
                ['status' => 'visited', 'name' => '已回访', 'count' => $stats['visited']??0]
            ]
        ];
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
        $field = 'follow_id,reserve_service_time,   
       taker_mobile,taker_full_address,taker_address,member_message, taker_name,service_finish_time,finish_time,service_time,create_time,
       order_name,category_id,store_id,order_id,site_id, member_id, order_no, technician_id,order_status, create_time, order_money, pay_money';
        $order = 'create_time desc';
        $join_where = [];
        if (isset($where['member_search']) && $where['member_search'] != '') $join_where[] = ['member.member_no|member.nickname|member.username|member.mobile', 'like', "%" . $where['member_search'] . "%"];
        if (isset($where['technician_name']) && $where['technician_name'] != '') $join_where[] = ['technician.real_name', 'like', "%" . $where['technician_name'] . "%"];
        if (isset($where['store_name']) && $where['store_name'] != '') $join_where[] = ['store.store_name', 'like', "%" . $where['store_name'] . "%"];
        if (isset($where['follow_status']) && $where['follow_status'] != '') {
            if ($where['follow_status'] == 'visited') {
                $join_where[] = ['order.follow_id', '>', 0];
            } else {
                $join_where[] = ['order.follow_id', '=', 0];
            }
        }
        $search_model = (new  Order)->where([['order.site_id', '=', $this->site_id], ['order.order_status', '=', OrderDict::FINISH]])
            ->where($join_where)
            ->withSearch(['order_no', 'join_create_time', 'order_name', 'order_from'], $where)->field($field)
            ->withJoin([
                'member' => ['nickname', 'member_id'],
                'technician' => ['real_name'],
                'store' => ['store_name', 'mobile', 'store_id'],
            ], 'left')
            ->with(
                [
                    'goodsCategory' => function ($query) {
                        $query->field('category_name,category_id');
                    },
                    'follow'
                ])
            ->order($order)->append(['order_status_info', 'follow_status_name', 'time_reminder']);
        $list = $this->pageQuery($search_model);
        foreach ($list['data'] as &$team) {
            $team['taker_full_address'] = $team['taker_full_address'] . $team['taker_address'];
        }
        return $list;
    }


    /**
     * 回访详情
     * @param array $where
     * @return mixed
     */
    public function getInfo(int $order_id)
    {
        $field = 'follow_staff,follow_id, fee_situation,order_id, result_feedback, satisfaction_score, follow_time,follow_summary,suggestion_content';
        $info = $this->model->where([['site_id', '=', $this->site_id], ['order_id', '=', $order_id]])
            ->field($field)
            ->with(
                [

                    'sysUser' => function ($query) {
                        $query->field('username,uid');
                    },

                ])
            ->append([])->findOrEmpty()->toArray();
        return $info;
    }


    public function follow($order_id, $data)
    {
        $follow_info = $this->getInfo($order_id);
        if (empty($follow_info)) {
            $data['order_id'] = $order_id;
            $this->add($data);
        } else {
            $this->edit($order_id, $data);
        }
        return true;
    }


    /**
     * 添加
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['follow_staff'] = $this->uid;
        $data['follow_time'] = time();
        try {
            $res = $this->model->create($data);
            (new  Order)->where([
                ['order_id', '=', $data['order_id']],
                ['site_id', '=', $this->site_id]
            ])->update(['follow_id' => $res->follow_id]);
            return $res->follow_id;
        } catch (\Exception $e) {
            throw new AdminException($e->getMessage());
        }
    }


    /**
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $order_id, array $data)
    {
        Db::startTrans();
        try {
            // 执行更新
            $this->model->where([
                ['order_id', '=', $order_id],
                ['site_id', '=', $this->site_id]
            ])->update($data);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new AdminException($e->getMessage());
        }
    }


}
