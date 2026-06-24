<?php

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\model\Sign as SignModel;
use addon\sd_xiaoyuan\app\adminapi\service\SignConfigService;
use core\base\BaseAdminController;

/**
 * 签到管理控制器
 */
class Sign extends BaseAdminController
{
    /**
     * 签到统计
     */
    public function stat()
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $weekStart = date('Y-m-d', strtotime('monday this week'));

        // 今日签到人数
        $todayCount = (new SignModel())->where([
            ['site_id', '=', $this->request->siteId()],
            ['sign_date', '=', $today]
        ])->count();

        // 昨日签到人数
        $yesterdayCount = (new SignModel())->where([
            ['site_id', '=', $this->request->siteId()],
            ['sign_date', '=', $yesterday]
        ])->count();

        // 本周签到人次
        $weekCount = (new SignModel())->where([
            ['site_id', '=', $this->request->siteId()],
            ['sign_date', '>=', $weekStart]
        ])->count();

        // 累计签到人次
        $totalCount = (new SignModel())->where([
            ['site_id', '=', $this->request->siteId()]
        ])->count();

        return success([
            'today_count' => $todayCount,
            'yesterday_count' => $yesterdayCount,
            'week_count' => $weekCount,
            'total_count' => $totalCount
        ]);
    }

    /**
     * 签到记录列表
     */
    public function lists()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['sign_date', ''],
            ['member_id', 0]
        ]);

        $where = [['site_id', '=', $this->request->siteId()]];

        if (!empty($params['sign_date'])) {
            $where[] = ['sign_date', '=', $params['sign_date']];
        }

        if ($params['member_id'] > 0) {
            $where[] = ['member_id', '=', $params['member_id']];
        }

        $model = new SignModel();
        $count = $model->where($where)->count();
        $list = $model->where($where)
            ->page($params['page'], $params['limit'])
            ->order('id desc')
            ->select()
            ->toArray();

        // 附加会员昵称
        $memberIds = array_unique(array_filter(array_column($list, 'member_id')));
        $members = [];
        if (!empty($memberIds)) {
            $memberList = \app\model\member\Member::where([['member_id', 'in', $memberIds]])->field('member_id,nickname,headimg')->select()->toArray();
            foreach ($memberList as $m) {
                $members[$m['member_id']] = $m;
            }
        }
        foreach ($list as &$item) {
            $mid = $item['member_id'] ?? 0;
            $item['member_nickname'] = $members[$mid]['nickname'] ?? '用户' . $mid;
            $item['member_headimg'] = $members[$mid]['headimg'] ?? '';
        }
        unset($item);

        return success(['count' => $count, 'list' => $list]);
    }

    /**
     * 签到配置（GET 读库/默认，POST 写入）
     */
    public function config()
    {
        $svc = new SignConfigService();
        if ($this->request->isPost()) {
            $config = $this->request->param('config', []);
            if (!is_array($config)) {
                if (is_string($config)) {
                    $tmp = json_decode($config, true);
                    $config = is_array($tmp) ? $tmp : [];
                } else {
                    $config = [];
                }
            }
            $svc->saveConfig($config);
            return success('保存成功');
        }
        return success($svc->getConfig());
    }
}
