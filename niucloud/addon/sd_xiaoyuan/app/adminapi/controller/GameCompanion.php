<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\model\GameCompanion as GameCompanionModel;
use addon\sd_xiaoyuan\app\service\core\MessageService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 游戏陪玩管理
 */
class GameCompanion extends BaseAdminController
{
    public function lists(): Response
    {
        $params = $this->request->params([
            ['game_type', ''],
            ['service_type', ''],
            ['status', ''],
            ['school_id', ''],
            ['keyword', ''],
            ['page', 1],
            ['limit', 10],
        ]);

        $where = [['site_id', '=', $this->request->siteId()]];

        if ($params['game_type'] !== '') {
            $where[] = ['game_type', '=', $params['game_type']];
        }
        if ($params['service_type'] !== '') {
            $where[] = ['service_type', '=', $params['service_type']];
        }
        if ($params['status'] !== '') {
            $where[] = ['status', '=', $params['status']];
        }
        if (!empty($params['school_id'])) {
            $where[] = ['school_id', '=', $params['school_id']];
        }
        if (!empty($params['keyword'])) {
            $where[] = ['title|nickname|game_name', 'like', '%' . $params['keyword'] . '%'];
        }

        $model = new GameCompanionModel();
        $list = $model->where($where)
            ->order('id desc')
            ->page(intval($params['page']), intval($params['limit']))
            ->select()
            ->toArray();

        $count = $model->where($where)->count();

        // 关联学校名称
        if (!empty($list)) {
            $school_ids = array_unique(array_filter(array_column($list, 'school_id')));
            $schools = [];
            if (!empty($school_ids)) {
                $schools = (new \addon\sd_xiaoyuan\app\model\School())->where([['id', 'in', $school_ids]])->column('name', 'id');
            }
            foreach ($list as &$item) {
                $item['school_name'] = $schools[$item['school_id'] ?? 0] ?? '';
            }
            unset($item);
        }

        return success([
            'list' => $list,
            'count' => $count
        ]);
    }

    public function detail(): Response
    {
        $id = intval($this->request->param('id', 0));
        $model = new GameCompanionModel();
        $info = $model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->request->siteId()]
        ])->findOrEmpty()->toArray();

        return success($info);
    }

    public function audit(): Response
    {
        $id = intval($this->request->param('id', 0));
        $status = intval($this->request->param('status', 0));
        $refuseReason = $this->request->param('refuse_reason', '');

        $model = new GameCompanionModel();
        $info = $model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->request->siteId()]
        ])->find();

        if (empty($info)) {
            return fail('记录不存在');
        }

        $update = [
            'status' => $status,
            'update_time' => time()
        ];
        if ($status == 3) {
            $update['refuse_reason'] = $refuseReason;
        }

        $model->where('id', $id)->update($update);

        // 通知发布者
        $msgService = new MessageService();
        if ($status == 1) {
            $msgService->sendDirect($this->request->siteId(), $info['member_id'], 'SYSTEM', '陪玩审核通过', '您发布的游戏陪玩信息已通过审核，已上架展示');
        } elseif ($status == 3) {
            $reason = $refuseReason ? "，原因：{$refuseReason}" : '';
            $msgService->sendDirect($this->request->siteId(), $info['member_id'], 'SYSTEM', '陪玩审核未通过', "您发布的游戏陪玩信息未通过审核{$reason}");
        }

        return success('操作成功');
    }

    public function setTop(): Response
    {
        $id = intval($this->request->param('id', 0));
        $isTop = intval($this->request->param('is_top', 0));

        $model = new GameCompanionModel();
        $model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->request->siteId()]
        ])->update([
            'is_top' => $isTop,
            'update_time' => time()
        ]);

        return success('操作成功');
    }

    public function del(): Response
    {
        $id = intval($this->request->param('id', 0));

        $model = new GameCompanionModel();
        $model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->request->siteId()]
        ])->delete();

        return success('删除成功');
    }

    public function stat(): Response
    {
        $model = new GameCompanionModel();
        $siteId = $this->request->siteId();

        $stat = [
            'total' => $model->where('site_id', $siteId)->count(),
            'pending' => $model->where([['site_id', '=', $siteId], ['status', '=', 0]])->count(),
            'online' => $model->where([['site_id', '=', $siteId], ['status', '=', 1]])->count(),
            'offline' => $model->where([['site_id', '=', $siteId], ['status', '=', 2]])->count(),
        ];

        return success($stat);
    }
}
