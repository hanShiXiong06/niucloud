<?php

namespace addon\sd_xiaoyuan\app\service\admin;

use addon\sd_xiaoyuan\app\model\CampusAuth;
use addon\sd_xiaoyuan\app\model\School;
use core\base\BaseService;

class CampusAuthService extends BaseService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new CampusAuth();
    }

    public function getList($siteId, $params)
    {
        $where = [['site_id', '=', $siteId]];
        
        if ($params['status'] !== '') {
            $where[] = ['status', '=', $params['status']];
        }
        
        if (!empty($params['identity_type'])) {
            $where[] = ['identity_type', '=', $params['identity_type']];
        }
        
        if (!empty($params['keyword'])) {
            $where[] = ['real_name|student_no|campus_name', 'like', '%' . $params['keyword'] . '%'];
        }
        
        $list = $this->model->where($where)
            ->order('create_time desc')
            ->page($params['page'], $params['limit'])
            ->select()
            ->toArray();
            
        $count = $this->model->where($where)->count();
        
        // 附加学校名称
        $school_ids = array_unique(array_filter(array_column($list, 'school_id')));
        $schools = [];
        if (!empty($school_ids)) {
            $school_list = (new School())->where([['id', 'in', $school_ids]])->field('id,name')->select()->toArray();
            foreach ($school_list as $s) {
                $schools[$s['id']] = $s['name'];
            }
        }
        // 附加会员信息
        $member_ids = array_unique(array_filter(array_column($list, 'member_id')));
        $members = [];
        if (!empty($member_ids)) {
            $member_list = (new \app\model\member\Member())->where([['member_id', 'in', $member_ids]])->field('member_id,nickname,headimg,mobile')->select()->toArray();
            foreach ($member_list as $m) {
                $members[$m['member_id']] = $m;
            }
        }

        foreach ($list as &$item) {
            $item['school_name'] = $schools[$item['school_id']] ?? ($item['campus_name'] ?: '未选择');
            $item['department'] = $item['college'] ?: ($item['major'] ?: '');
            $member = $members[$item['member_id']] ?? [];
            $item['member_nickname'] = $member['nickname'] ?? '';
            $item['member_headimg'] = $member['headimg'] ?? '';
            $item['member_mobile'] = $member['mobile'] ?? '';
        }
        unset($item);
        
        return [
            'list' => $list,
            'count' => $count
        ];
    }

    public function getDetail($id, $siteId)
    {
        return $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $siteId]
        ])->findOrEmpty()->toArray();
    }

    public function audit($id, $siteId, $status, $refuseReason = '')
    {
        $auth = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $siteId]
        ])->findOrEmpty();

        if ($auth->isEmpty()) return false;

        $auth->save([
            'status' => $status,
            'refuse_reason' => $refuseReason,
            'audit_time' => time(),
            'update_time' => time()
        ]);

        // 认证通过时，初始化信誉分为100
        if ($status == 1) {
            $creditModel = new \addon\sd_xiaoyuan\app\model\Credit();
            $exists = $creditModel->where([
                ['member_id', '=', $auth['member_id']],
                ['site_id', '=', $siteId]
            ])->findOrEmpty();
            if ($exists->isEmpty()) {
                $creditModel->create([
                    'member_id' => $auth['member_id'],
                    'site_id' => $siteId,
                    'score' => \addon\sd_xiaoyuan\app\model\Credit::INIT_SCORE,
                    'total_complete' => 0,
                    'total_cancel' => 0,
                    'total_complaint' => 0,
                    'is_restricted' => 0,
                    'create_time' => time(),
                    'update_time' => time()
                ]);
            }
        }

        return true;
    }

    public function getStat($siteId)
    {
        return [
            'total' => $this->model->where('site_id', $siteId)->count(),
            'pending' => $this->model->where([
                ['site_id', '=', $siteId],
                ['status', '=', 0]
            ])->count(),
            'approved' => $this->model->where([
                ['site_id', '=', $siteId],
                ['status', '=', 1]
            ])->count(),
            'rejected' => $this->model->where([
                ['site_id', '=', $siteId],
                ['status', '=', 2]
            ])->count()
        ];
    }

    public function cancel($id, $siteId)
    {
        $auth = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $siteId]
        ])->findOrEmpty();

        if ($auth->isEmpty()) return false;

        // 取消认证，将状态改为待审核
        $auth->save([
            'status' => 0,
            'refuse_reason' => '',
            'audit_time' => 0,
            'update_time' => time()
        ]);

        return true;
    }
}
