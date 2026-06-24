<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\model\LostFound as LostFoundModel;
use addon\sd_xiaoyuan\app\service\core\LostFoundService;
use core\base\BaseApiController;
use think\Response;

/**
 * 失物招领接口
 */
class LostFound extends BaseApiController
{
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
     * 获取统计
     */
    public function stats(): Response
    {
        $school_id = $this->request->param('school_id', 0);
        $type = $this->request->param('type', '');
        $data = (new LostFoundService())->getStats((int)$school_id, (string)$type);
        return success($data);
    }

    /**
     * 获取列表
     */
    public function list(): Response
    {
        $data = $this->request->params([
            ['type', ''],
            ['category', ''],
            ['keyword', ''],
            ['school_id', ''],
            ['campus', ''],
            ['all_school', 0],
            ['is_urgent', 0],
            ['page', 1],
            ['limit', 10],
        ]);
        
        $data['status'] = [LostFoundModel::STATUS_ACTIVE];
        
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
        
        $info = (new LostFoundService())->getInfo((int)$id, true);
        
        return success($info);
    }

    /**
     * 发布失物/招领
     */
    public function publish(): Response
    {
        $data = $this->request->params([
            ['type', ''],
            ['category', ''],
            ['title', ''],
            ['content', ''],
            ['images', ''],
            ['lost_time', 0],
            ['lost_address', ''],
            ['contact_name', ''],
            ['contact_mobile', ''],
            ['contact_wechat', ''],
            ['reward', ''],
            ['is_urgent', 0],
            ['school_id', 0],
            ['campus', ''],
        ]);
        
        if (empty($data['type']) || empty($data['title'])) {
            return fail('请填写完整信息');
        }
        
        $id = (new LostFoundService())->publish($this->request->memberId(), $data);
        return success(['id' => $id]);
    }

    /**
     * 编辑
     */
    public function edit(): Response
    {
        $id = $this->request->param('id', 0);
        $data = $this->request->params([
            ['category', ''],
            ['title', ''],
            ['content', ''],
            ['images', ''],
            ['lost_time', ''],
            ['lost_address', ''],
            ['contact_name', ''],
            ['contact_mobile', ''],
            ['contact_wechat', ''],
            ['reward', ''],
        ]);
        
        if (empty($id)) {
            return fail('参数错误');
        }
        
        (new LostFoundService())->edit((int)$id, $this->request->memberId(), $data);
        return success('编辑成功');
    }

    /**
     * 标记已找到/已归还
     */
    public function resolve(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }
        
        (new LostFoundService())->resolve((int)$id, $this->request->memberId());
        return success('操作成功');
    }

    /**
     * 关闭
     */
    public function close(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }
        
        (new LostFoundService())->close((int)$id, $this->request->memberId());
        return success('关闭成功');
    }

    /**
     * 删除
     */
    public function del(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }
        
        (new LostFoundService())->del((int)$id, $this->request->memberId());
        return success('删除成功');
    }

    /**
     * 联系发布者
     */
    public function contact(): Response
    {
        $id = $this->request->param('id', 0);
        $message = $this->request->param('message', '');
        
        if (empty($id)) {
            return fail('参数错误');
        }
        
        $info = (new LostFoundService())->contact((int)$id, $this->request->memberId(), $message);
        return success($info);
    }

    /**
     * 我发布的
     */
    public function myPublish(): Response
    {
        $data = $this->request->params([
            ['type', ''],
            ['status', ''],
            ['page', 1],
            ['limit', 10],
        ]);
        
        $service = new LostFoundService();
        $list = $service->getMyPage($data);
        return success($list);
    }
}
