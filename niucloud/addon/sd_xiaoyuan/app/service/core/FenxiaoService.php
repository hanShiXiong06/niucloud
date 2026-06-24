<?php

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\MemberRelation;
use addon\sd_xiaoyuan\app\model\order\Order;
use app\model\member\MemberAccountLog;
use app\service\core\member\CoreMemberAccountService;
use app\dict\member\MemberAccountTypeDict;
use core\base\BaseApiService;

/**
 * 分销服务
 */
class FenxiaoService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 设置推荐关系
     */
    public function setRelation($member_id, $pid)
    {
        if (empty($member_id) || empty($pid)) {
            return false;
        }

        // 不能自己推荐自己
        if ($member_id == $pid) {
            return false;
        }

        // 检查是否已有推荐关系
        $relation = (new MemberRelation())->where([
            ['member_id', '=', $member_id],
            ['site_id', '=', $this->site_id]
        ])->find();

        if (!empty($relation)) {
            return true; // 已有推荐关系，不再修改
        }

        // 获取上级的推荐关系，确定二级推荐人
        $parent_relation = (new MemberRelation())->where([
            ['member_id', '=', $pid],
            ['site_id', '=', $this->site_id]
        ])->find();

        $pid2 = 0;
        if (!empty($parent_relation)) {
            $pid2 = $parent_relation->pid;
        }

        // 创建推荐关系
        (new MemberRelation())->create([
            'member_id' => $member_id,
            'pid' => $pid,
            'pid2' => $pid2,
            'site_id' => $this->site_id,
            'create_time' => time()
        ]);

        return true;
    }

    /**
     * 获取推荐关系
     */
    public function getRelation($member_id = null)
    {
        if ($member_id === null) {
            $member_id = request()->memberId();
        }
        return (new MemberRelation())->where([
            ['member_id', '=', $member_id],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty()->toArray();
    }

    
    /**
     * 订单完成时发放分销佣金
     */
    public function sendCommission($order_id)
    {
        $order = (new Order())->where('id', $order_id)->find();
       
        if (empty($order)) {
            return false;
        }

        // 检查是否已发放
        if (!empty($order['fenxiao_status']) && $order['fenxiao_status'] == 1) {
            return false;
        }

        // 获取配置
        $configService = new ConfigService();
        $config = $configService->getConfig($order['site_id'], 'fenxiao');
        
        if (empty($config['open_fenxiao'])) {
            return false;
        }

        // 设置site_id用于查询推荐关系
        $this->site_id = $order['site_id'];
        
        // 获取推荐关系
        $relation = $this->getRelation($order['member_id']);
        
        if (empty($relation) || empty($relation['pid'])) {
            return false;
        }

        $pid = $relation['pid'];
        $pid2 = $relation['pid2'] ?? 0;

        // 计算佣金
        $rate1 = floatval($config['fenxiao_rate1'] ?? 10) / 100;
        $rate2 = floatval($config['fenxiao_rate2'] ?? 5) / 100;
        
        $commission1 = round($order['actual_fee'] * $rate1, 2);
        $commission2 = round($order['actual_fee'] * $rate2, 2);

        // 发放一级佣金
        if ($commission1 > 0 && $pid > 0) {
            try {
                (new CoreMemberAccountService())->addLog(
                    $order['site_id'],
                    $pid,
                    MemberAccountTypeDict::COMMISSION,
                    $commission1,
                    'sd_xiaoyuan_commission',
                    '校园帮邀请佣金',
                    $order['id']
                );

                // 更新累计佣金
                (new MemberRelation())->where([
                    ['member_id', '=', $pid],
                    ['site_id', '=', $order['site_id']]
                ])->inc('total_commission', $commission1)->update();
            } catch (\Exception $e) {
                trace('一级佣金发放失败：' . $e->getMessage(), 'error');
            }
        }

        // 发放二级佣金
        if ($pid2 > 0 && $commission2 > 0) {
            try {
                (new CoreMemberAccountService())->addLog(
                    $order['site_id'],
                    $pid2,
                    MemberAccountTypeDict::COMMISSION,
                    $commission2,
                    'sd_xiaoyuan_commission',
                    '校园帮邀请佣金',
                    $order['id']
                );

                // 更新累计佣金
                (new MemberRelation())->where([
                    ['member_id', '=', $pid2],
                    ['site_id', '=', $order['site_id']]
                ])->inc('total_commission', $commission2)->update();
            } catch (\Exception $e) {
                trace('二级佣金发放失败：' . $e->getMessage(), 'error');
            }
        }

        return true;
    }

    /**
     * 获取邀请海报数据
     */
    public function getPosterData()
    {
        $member_id = request()->memberId();
        $poster_service = new \app\service\core\poster\CorePosterService();
        try {
            $poster = $poster_service->get(
                $this->site_id,
                '',
                'xiaoyuan_invite',
                ['id' => $member_id, 'member_id' => $member_id],
                '',
                false
            );
            return ['poster' => $poster ?: ''];
        } catch (\Exception $e) {
            return ['poster' => '', 'error' => $e->getMessage()];
        }
    }

    /**
     * 获取邀请统计
     */
    public function getInviteStat()
    {
        $member_id = request()->memberId();

        $level1Count = (new MemberRelation())->where([
            ['pid', '=', $member_id],
            ['site_id', '=', $this->site_id]
        ])->count();

        $level2Count = (new MemberRelation())->where([
            ['pid2', '=', $member_id],
            ['site_id', '=', $this->site_id]
        ])->count();

        $totalCommission = (new MemberAccountLog())->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $member_id],
            ['account_type', '=', MemberAccountTypeDict::COMMISSION],
            ['from_type', '=', 'sd_xiaoyuan_commission'],
        ])->sum('account_data');

        return [
            'total_commission' => number_format((float)$totalCommission, 2, '.', ''),
            'total_invite' => $level1Count + $level2Count,
            'level1_count' => $level1Count,
            'level2_count' => $level2Count
        ];
    }

    /**
     * 获取团队统计
     */
    public function getTeamStat()
    {
        $member_id = request()->memberId();
        
        // 一级下级数量
        $level1 = (new MemberRelation())->where([
            ['pid', '=', $member_id],
            ['site_id', '=', $this->site_id]
        ])->count();

        // 二级下级数量
        $level2 = (new MemberRelation())->where([
            ['pid2', '=', $member_id],
            ['site_id', '=', $this->site_id]
        ])->count();

        return [
            'total' => $level1 + $level2,
            'level1' => $level1,
            'level2' => $level2
        ];
    }

    /**
     * 获取团队列表
     */
    public function getTeamList(int $level = 1, int $page = 1, int $limit = 20)
    {
        $member_id = request()->memberId();
        
        $field = $level == 1 ? 'pid' : 'pid2';
        
        $relations = (new MemberRelation())->where([
            [$field, '=', $member_id],
            ['site_id', '=', $this->site_id]
        ])->page($page, $limit)->order('create_time desc')->select()->toArray();
        
        $list = [];
        if (!empty($relations)) {
            $member_ids = array_column($relations, 'member_id');
            $members = (new \app\model\member\Member())->where([['member_id', 'in', $member_ids]])->column('nickname,headimg,create_time', 'member_id');
            
            // 获取订单数
            $orderCounts = (new Order())->where([['member_id', 'in', $member_ids], ['site_id', '=', $this->site_id]])->group('member_id')->column('count(*) as cnt', 'member_id');
            
            foreach ($relations as $r) {
                $mid = $r['member_id'];
                $createTime = (int)($r['create_time'] ?? 0);
                $list[] = [
                    'member_id' => $mid,
                    'nickname' => $members[$mid]['nickname'] ?? '',
                    'headimg' => $members[$mid]['headimg'] ?? '',
                    'create_time' => $createTime > 0 ? date('Y-m-d', $createTime) : '',
                    'order_count' => $orderCounts[$mid] ?? 0
                ];
            }
        }
        
        return ['list' => $list];
    }
}
