<?php

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\model\HouseOrder as HouseOrderModel;
use addon\sd_xiaoyuan\app\model\House;
use core\base\BaseAdminController;

class HouseOrder extends BaseAdminController
{
    /**
     * 房源订单列表
     */
    public function lists()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['status', ''],
            ['house_id', 0]
        ]);

        $where = [['site_id', '=', $this->request->siteId()]];

        if ($params['status'] !== '') {
            $where[] = ['status', '=', $params['status']];
        }

        if ($params['house_id'] > 0) {
            $where[] = ['house_id', '=', $params['house_id']];
        }

        $model = new HouseOrderModel();
        $count = $model->where($where)->count();
        $list = $model->where($where)
            ->page((int)$params['page'], (int)$params['limit'])
            ->order('id desc')
            ->select()
            ->toArray();

        // 附加房源信息
        $houseIds = array_column($list, 'house_id');
        if (!empty($houseIds)) {
            $houses = (new House())
                ->whereIn('id', $houseIds)
                ->column('title,address,rent_price,images', 'id');
            foreach ($list as &$item) {
                $item['house'] = $houses[$item['house_id']] ?? [];
            }
        }

        // 附加会员信息
        $memberIds = array_column($list, 'member_id');
        if (!empty($memberIds)) {
            $members = \app\model\member\Member::whereIn('member_id', $memberIds)
                ->column('nickname,headimg,mobile', 'member_id');
            foreach ($list as &$item) {
                $item['member'] = $members[$item['member_id']] ?? [];
            }
        }

        return success(['count' => $count, 'list' => $list]);
    }

    /**
     * 处理订单（接受/拒绝）
     */
    public function handle()
    {
        $id = $this->request->param('id');
        $status = $this->request->param('status', 1);
        $remark = $this->request->param('remark', '');

        $order = (new HouseOrderModel())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->request->siteId()]
        ])->find();

        if (empty($order)) {
            return $this->error('订单不存在');
        }

        $order->save([
            'status' => $status,
            'remark' => $remark,
            'update_time' => time()
        ]);

        return success('操作成功');
    }

    /**
     * 退押金（只退押金）
     */
    public function refundDeposit()
    {
        $id = $this->request->param('id');

        $order = (new HouseOrderModel())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->request->siteId()]
        ])->find();

        if (empty($order)) {
            return $this->error('订单不存在');
        }

        if ($order['deposit_refunded'] == 1) {
            return $this->error('押金已退款');
        }

        $order->save([
            'deposit_refunded' => 1,
            'deposit_refund_time' => time(),
            'update_time' => time()
        ]);

        return success('押金退款成功');
    }

    /**
     * 全额退款（押金+支付金额）
     */
    public function refundAll()
    {
        $id = $this->request->param('id');

        $order = (new HouseOrderModel())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->request->siteId()]
        ])->find();

        if (empty($order)) {
            return $this->error('订单不存在');
        }

        if ($order['refund_status'] == 1) {
            return $this->error('订单已退款');
        }

        $order->save([
            'refund_status' => 1,
            'deposit_refunded' => 1,
            'refund_time' => time(),
            'deposit_refund_time' => time(),
            'status' => 4, // 已退款状态
            'update_time' => time()
        ]);

        return success('全额退款成功');
    }
}
