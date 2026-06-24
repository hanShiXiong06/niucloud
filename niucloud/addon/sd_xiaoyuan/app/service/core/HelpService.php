<?php

namespace addon\sd_xiaoyuan\app\service\core;

use core\base\BaseApiService;
use addon\sd_xiaoyuan\app\model\HelpOrder;

class HelpService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new HelpOrder();
    }

    /**
     * 创建帮帮忙订单
     */
    public function create(array $data)
    {
        $insertData = [
            'site_id' => $this->site_id,
            'member_id' => $this->member_id,
            'help_type' => $data['help_type'],
            'gender_limit' => $data['gender_limit'],
            'images' => $data['images'],
            'remark' => $data['remark'],
            'address_id' => $data['address_id'],
            'reward' => $data['reward'],
            'status' => 0,
            'create_time' => time()
        ];

        $id = $this->model->insertGetId($insertData);
        return ['id' => $id];
    }

    /**
     * 获取帮帮忙列表
     */
    public function getList(array $params)
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['status', 'in', [0, 1]]
        ];

        if (!empty($params['help_type'])) {
            $where[] = ['help_type', '=', $params['help_type']];
        }

        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 10;

        $count = $this->model->where($where)->count();
        $list = $this->model->where($where)
            ->page($page, $limit)
            ->order('create_time desc')
            ->select()
            ->toArray();

        return [
            'count' => $count,
            'list' => $list
        ];
    }

    /**
     * 获取帮帮忙详情
     */
    public function getDetail($id)
    {
        $info = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->find();

        if (empty($info)) {
            throw new \Exception('帮帮忙订单不存在');
        }

        return $info->toArray();
    }

    /**
     * 接受帮帮忙
     */
    public function accept($id)
    {
        $info = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id],
            ['status', '=', 0]
        ])->find();

        if (empty($info)) {
            throw new \Exception('帮帮忙订单不存在或已被接单');
        }

        if ($info['member_id'] == $this->member_id) {
            throw new \Exception('不能接受自己发布的帮帮忙');
        }

        $this->model->where('id', $id)->update([
            'helper_id' => $this->member_id,
            'status' => 1,
            'accept_time' => time()
        ]);

        return true;
    }

    /**
     * 取消帮帮忙
     */
    public function cancel($id, $reason = '')
    {
        $info = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['status', 'in', [0, 1]]
        ])->find();

        if (empty($info)) {
            throw new \Exception('帮帮忙订单不存在或无法取消');
        }

        $this->model->where('id', $id)->update([
            'status' => 3,
            'cancel_reason' => $reason,
            'cancel_time' => time()
        ]);

        return true;
    }

    /**
     * 完成帮帮忙
     */
    public function complete($id)
    {
        $info = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['status', '=', 1]
        ])->find();

        if (empty($info)) {
            throw new \Exception('帮帮忙订单不存在或无法完成');
        }

        $this->model->where('id', $id)->update([
            'status' => 2,
            'complete_time' => time()
        ]);

        return true;
    }

    /**
     * 我发布的帮帮忙
     */
    public function getMyPublish(array $params)
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id]
        ];

        if ($params['status'] !== '') {
            $where[] = ['status', '=', $params['status']];
        }

        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 10;

        $count = $this->model->where($where)->count();
        $list = $this->model->where($where)
            ->page($page, $limit)
            ->order('create_time desc')
            ->select()
            ->toArray();

        return [
            'count' => $count,
            'list' => $list
        ];
    }

    /**
     * 我接受的帮帮忙
     */
    public function getMyAccept(array $params)
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['helper_id', '=', $this->member_id]
        ];

        if ($params['status'] !== '') {
            $where[] = ['status', '=', $params['status']];
        }

        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 10;

        $count = $this->model->where($where)->count();
        $list = $this->model->where($where)
            ->page($page, $limit)
            ->order('create_time desc')
            ->select()
            ->toArray();

        return [
            'count' => $count,
            'list' => $list
        ];
    }
}
