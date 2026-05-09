<?php

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\Evaluate;
use addon\sd_xiaoyuan\app\model\order\Order;
use addon\sd_xiaoyuan\app\model\runner\Runner;
use core\base\BaseApiService;
use core\exception\CommonException;

class EvaluateService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Evaluate();
    }

    /**
     * 添加评价
     */
    public function add($data)
    {
        // 检查订单是否存在且已完成
        $order = (new Order())->where([
            ['id', '=', $data['order_id']],
            ['site_id', '=', $this->site_id]
        ])->find();

        if (empty($order)) {
            throw new CommonException('订单不存在');
        }

        if ($order['member_id'] != $this->member_id) {
            throw new CommonException('无权评价此订单');
        }

        if ($order['status'] != 50) {
            throw new CommonException('订单未完成，无法评价');
        }

        // 检查是否已评价
        $exist = $this->model->where([
            ['order_id', '=', $data['order_id']],
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id]
        ])->find();

        if ($exist) {
            throw new CommonException('您已评价过此订单');
        }

        $evaluateData = [
            'site_id' => $this->site_id,
            'member_id' => $this->member_id,
            'order_id' => $data['order_id'],
            'runner_id' => $order['runner_id'],
            'score' => $data['score'] ?? 5,
            'service_score' => $data['service_score'] ?? 5,
            'speed_score' => $data['speed_score'] ?? 5,
            'content' => $data['content'] ?? '',
            'images' => is_array($data['images'] ?? []) ? json_encode($data['images']) : ($data['images'] ?? ''),
            'is_anonymous' => $data['is_anonymous'] ?? 0,
            'create_time' => time()
        ];

        $result = $this->model->create($evaluateData);

        // 通知接单员（member_id 须为会员 ID；消息 type 须为 xiaoyuan_message 表 enum 内取值，不能用不存在的 EVALUATE）
        if (!empty($order['runner_id'])) {
            try {
                $runnerMemberId = (int)(new Runner())->where('id', (int)$order['runner_id'])->value('member_id');
                if ($runnerMemberId > 0) {
                    (new MessageService())->send(
                        $runnerMemberId,
                        'ORDER',
                        '收到新的评价',
                        '用户对您的服务进行了评价，点击查看详情',
                        [
                            'link_type' => 'evaluate_detail',
                            'link_id' => $result->id,
                            'order_id' => $data['order_id'],
                            'evaluate_id' => $result->id,
                        ]
                    );
                }
            } catch (\Throwable $e) {
                trace('sd_xiaoyuan evaluate notify runner: ' . $e->getMessage(), 'error');
            }
        }

        return ['id' => $result->id];
    }

    /**
     * 获取跑腿员评价列表
     */
    public function getRunnerEvaluates($runnerId, $params)
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['runner_id', '=', $runnerId]
        ];

        $list = $this->model->where($where)
            ->order('create_time desc')
            ->page($params['page'], $params['limit'])
            ->select()
            ->toArray();

        $count = $this->model->where($where)->count();
        
        // 附加会员头像和昵称
        if (!empty($list)) {
            $member_ids = array_unique(array_filter(array_column($list, 'member_id')));
            if (!empty($member_ids)) {
                $members = (new \app\model\member\Member())->where([['member_id', 'in', $member_ids]])->column('nickname,headimg', 'member_id');
                foreach ($list as &$item) {
                    $mid = $item['member_id'] ?? 0;
                    $item['member_nickname'] = $members[$mid]['nickname'] ?? '';
                    $item['member_headimg'] = $members[$mid]['headimg'] ?? '';
                }
            }
        }

        return [
            'list' => $list,
            'count' => $count
        ];
    }

    /**
     * 获取我的评价列表
     */
    public function getMyEvaluates($params)
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id]
        ];

        $list = $this->model->where($where)
            ->order('create_time desc')
            ->page($params['page'], $params['limit'])
            ->select()
            ->toArray();

        $count = $this->model->where($where)->count();

        return [
            'list' => $list,
            'count' => $count
        ];
    }
}
