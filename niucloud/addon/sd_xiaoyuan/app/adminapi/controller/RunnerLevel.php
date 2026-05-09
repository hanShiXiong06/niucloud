<?php

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\model\RunnerLevel as LevelModel;
use addon\sd_xiaoyuan\app\model\runner\Runner;
use addon\sd_xiaoyuan\app\model\MemberRelation;
use app\service\core\sys\CoreConfigService;
use core\base\BaseAdminController;

/**
 * 接单员等级管理控制器
 */
class RunnerLevel extends BaseAdminController
{
    /**
     * 等级列表
     */
    public function lists()
    {
        $list = (new LevelModel())->where('site_id', $this->request->siteId())
            ->order('level asc')
            ->select()
            ->toArray();

        // 如果没有配置，返回默认等级
        if (empty($list)) {
            $list = [
                ['level' => 1, 'name' => '新手接单员', 'min_orders' => 0, 'commission_rate' => 70, 'icon' => '', 'status' => 1],
                ['level' => 2, 'name' => '初级接单员', 'min_orders' => 50, 'commission_rate' => 75, 'icon' => '', 'status' => 1],
                ['level' => 3, 'name' => '中级接单员', 'min_orders' => 200, 'commission_rate' => 80, 'icon' => '', 'status' => 1],
                ['level' => 4, 'name' => '高级接单员', 'min_orders' => 500, 'commission_rate' => 85, 'icon' => '', 'status' => 1],
                ['level' => 5, 'name' => '金牌接单员', 'min_orders' => 1000, 'commission_rate' => 90, 'icon' => '', 'status' => 1]
            ];
            
            // 为默认等级添加各类型佣金比例字段（默认为空，使用默认比例）
                foreach ($list as &$item) {
                    $rateFields = ['express', 'buy', 'errand', 'send', 'print', 'queue', 'seat', 'carry', 'trash', 'clean', 'help', 'game', 'group'];
                    foreach ($rateFields as $field) {
                        $item['rate_' . $field] = null;
                    }
                }
        }

        return success($list);
    }

    /**
     * 保存等级配置
     */
    public function save()
    {
        $levels = $this->request->param('levels', []);

        if (empty($levels)) {
            return $this->error('请配置等级');
        }

        // 删除旧配置
        (new LevelModel())->where('site_id', $this->request->siteId())->delete();

        // 保存新配置
        foreach ($levels as $item) {
            $data = [
                'site_id' => $this->request->siteId(),
                'level' => $item['level'],
                'name' => $item['name'],
                'min_orders' => $item['min_orders'],
                'commission_rate' => $item['commission_rate'],
                'icon' => $item['icon'] ?? '',
                'status' => $item['status'] ?? 1,
                'create_time' => time()
            ];
            
            // 添加各类型佣金比例字段
            $rateFields = ['express', 'buy', 'errand', 'send', 'print', 'queue', 'seat', 'carry', 'trash', 'clean', 'help', 'game', 'group'];
            foreach ($rateFields as $field) {
                $rateKey = 'rate_' . $field;
                if (isset($item[$rateKey]) && $item[$rateKey] !== null && $item[$rateKey] !== '') {
                    $data[$rateKey] = $item[$rateKey];
                }
            }
            
            LevelModel::create($data);
        }

        return success('保存成功');
    }

    /**
     * 获取邀请奖励配置
     */
    public function getInviteConfig()
    {
        $config = [
            'reward_amount' => 10.00,
            'reward_type' => 'balance',
            'require_orders' => 1,
            'is_enabled' => 1
        ];

        // 从框架配置获取
        $saved = (new CoreConfigService())->getConfig($this->request->siteId(), 'SD_XIAOYUAN_RUNNER_INVITE');
        if (!empty($saved['value'])) {
            $config = array_merge($config, $saved['value']);
        }

        return success($config);
    }

    /**
     * 保存邀请奖励配置
     */
    public function saveInviteConfig()
    {
        $config = $this->request->params([
            ['reward_amount', 10.00],
            ['reward_type', 'balance'],
            ['require_orders', 1],
            ['is_enabled', 1]
        ]);

        (new CoreConfigService())->setConfig($this->request->siteId(), 'SD_XIAOYUAN_RUNNER_INVITE', $config);

        return success('保存成功');
    }

    /**
     * 邀请奖励记录列表
     */
    public function inviteRewardList()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10]
        ]);

        $model = new MemberRelation();
        $where = [
            ['site_id', '=', $this->request->siteId()],
            ['pid', '>', 0]
        ];

        $count = $model->where($where)->count();
        $list = $model->where($where)
            ->field('id,member_id,pid,pid2,create_time')
            ->order('id desc')
            ->page(intval($params['page']), intval($params['limit']))
            ->select()
            ->toArray();

        // 附加会员昵称
        $memberIds = array_unique(array_merge(
            array_column($list, 'member_id'),
            array_column($list, 'pid')
        ));
        $members = [];
        if (!empty($memberIds)) {
            $memberList = \app\model\member\Member::where([['member_id', 'in', $memberIds]])->field('member_id,nickname,headimg')->select()->toArray();
            foreach ($memberList as $m) {
                $members[$m['member_id']] = $m;
            }
        }
        foreach ($list as &$item) {
            $item['member_nickname'] = $members[$item['member_id']]['nickname'] ?? '';
            $item['inviter_nickname'] = $members[$item['pid']]['nickname'] ?? '';
        }
        unset($item);

        return success(['list' => $list, 'count' => $count]);
    }

    /**
     * 邀请统计
     */
    public function inviteStats()
    {
        $model = new MemberRelation();
        
        // 总邀请人数
        $totalInvited = $model->where([
            ['site_id', '=', $this->request->siteId()],
            ['pid', '>', 0]
        ])->count();
        
        return success([
            'total_invited' => $totalInvited,
            'reward_granted' => 0
        ]);
    }
}
