<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\service\core\LostFoundService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 失物招领管理控制器
 */
class LostFound extends BaseAdminController
{
    /**
     * 获取列表
     */
    public function lists(): Response
    {
        $data = $this->request->params([
            ['type', ''],
            ['status', ''],
            ['school_id', ''],
            ['campus', ''],
            ['category', ''],
            ['keyword', ''],
            ['page', 1],
            ['limit', 10],
        ]);
        
        $list = (new LostFoundService())->getPage($data);
        return success($list);
    }

    /**
     * 获取详情
     */
    public function info(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }
        
        $info = (new LostFoundService())->getInfo((int)$id);
        return success($info);
    }

    /**
     * 获取类型列表
     */
    public function typeList(): Response
    {
        $list = (new LostFoundService())->getTypeList();
        return success($list);
    }

    /**
     * 获取分类列表
     */
    public function categoryList(): Response
    {
        $list = (new LostFoundService())->getCategoryList();
        return success($list);
    }

    /**
     * 审核信息
     */
    public function audit(): Response
    {
        $data = $this->request->params([
            ['id', 0],
            ['status', 0],
            ['refuse_reason', ''],
        ]);
        
        if (empty($data['id'])) {
            return fail('参数错误');
        }
        
        $item = (new \addon\sd_xiaoyuan\app\model\LostFound())->where([
            ['id', '=', $data['id']], 
            ['site_id', '=', $this->request->siteId()]
        ])->find();
        
        if (empty($item)) {
            return fail('信息不存在');
        }
        
        // status: 1=通过审核(进行中), 2=拒绝审核(已关闭)
        $updateData = [];
        if ($data['status'] == 1) {
            // 审核通过，设置为进行中状态
            $updateData['status'] = \addon\sd_xiaoyuan\app\model\LostFound::STATUS_ACTIVE;
            $updateData['refuse_reason'] = '';
        } else if ($data['status'] == 2) {
            // 审核拒绝，设置为已关闭状态
            $updateData['status'] = \addon\sd_xiaoyuan\app\model\LostFound::STATUS_CLOSED;
            $updateData['refuse_reason'] = $data['refuse_reason'] ?? '审核未通过';
        } else {
            return fail('状态参数错误');
        }
        
        $updateData['update_time'] = time();
        $item->save($updateData);
        
        return success($data['status'] == 1 ? '审核通过' : '已拒绝');
    }

    /**
     * 删除信息
     */
    public function delete(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }
        
        $item = (new \addon\sd_xiaoyuan\app\model\LostFound())->where([
            ['id', '=', $id], 
            ['site_id', '=', $this->request->siteId()]
        ])->find();
        
        if (empty($item)) {
            return fail('信息不存在');
        }
        
        $item->delete();
        return success('删除成功');
    }
}
