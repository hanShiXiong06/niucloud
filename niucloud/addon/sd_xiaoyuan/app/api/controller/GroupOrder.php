<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\GroupOrderService;
use core\base\BaseApiController;
use think\Response;

/**
 * 拼单好饭接口
 */
class GroupOrder extends BaseApiController
{
    /**
     * 获取拼单类型列表
     */
    public function typeList(): Response
    {
        $list = (new GroupOrderService())->getTypeList();
        return success($list);
    }

    /**
     * 获取拼单列表
     */
    public function list(): Response
    {
        $data = $this->request->params([
            ['group_type', ''],
            ['status', ''],
            ['school_id', ''],
            ['campus', ''],
            ['page', 1],
            ['limit', 10],
        ]);
        
        $list = (new GroupOrderService())->getPage($data);
        return success($list);
    }

    /**
     * 获取拼单统计
     */
    public function stats(): Response
    {
        $data = (new GroupOrderService())->getStats();
        return success($data);
    }

    /**
     * 获取拼单详情
     */
    public function info(): Response
    {
        $id = $this->request->param('id', 0);
        if (empty($id)) {
            return fail('参数错误');
        }
        
        $info = (new GroupOrderService())->getInfo((int)$id);
        return success($info);
    }

    /**
     * 发起拼单
     */
    public function create(): Response
    {
        $data = $this->request->params([
            ['group_type', ''],
            ['title', ''],
            ['content', ''],
            ['images', ''],
            ['shop_name', ''],
            ['shop_address', ''],
            ['delivery_address', ''],
            ['delivery_lng', ''],
            ['delivery_lat', ''],
            ['min_members', 2],
            ['max_members', 10],
            ['per_price', 0],
            ['delivery_fee', 0],
            ['deadline', 0],
            ['order_content', ''],
            ['school_id', 0],
            ['campus', ''],
        ]);
        
        if (empty($data['group_type']) || empty($data['title'])) {
            return fail('请填写完整信息');
        }
        
        $member_id = $this->request->memberId();
        $id = (new GroupOrderService())->create($member_id, $data);
        return success(['id' => $id]);
    }

    /**
     * 参与拼单
     */
    public function join(): Response
    {
        $data = $this->request->params([
            ['group_id', 0],
            ['order_content', ''],
            ['amount', 0],
        ]);
        
        if (empty($data['group_id'])) {
            return fail('参数错误');
        }
        
        $member_id = $this->request->memberId();
        (new GroupOrderService())->join((int)$data['group_id'], $member_id, $data);
        return success('参与成功');
    }

    /**
     * 退出拼单
     */
    public function quit(): Response
    {
        $group_id = $this->request->param('group_id', 0);
        if (empty($group_id)) {
            return fail('参数错误');
        }
        
        $member_id = $this->request->memberId();
        (new GroupOrderService())->quit((int)$group_id, $member_id);
        return success('退出成功');
    }

    /**
     * 取消拼单
     */
    public function cancel(): Response
    {
        $group_id = $this->request->param('group_id', 0);
        if (empty($group_id)) {
            return fail('参数错误');
        }
        
        $member_id = $this->request->memberId();
        (new GroupOrderService())->cancel((int)$group_id, $member_id);
        return success('取消成功');
    }

    /**
     * 确认成团
     */
    public function confirmSuccess(): Response
    {
        $group_id = $this->request->param('group_id', 0);
        if (empty($group_id)) {
            return fail('参数错误');
        }
        
        $member_id = $this->request->memberId();
        (new GroupOrderService())->confirmSuccess((int)$group_id, $member_id);
        return success('成团成功');
    }

    /**
     * 完成拼单
     */
    public function complete(): Response
    {
        $group_id = $this->request->param('group_id', 0);
        if (empty($group_id)) {
            return fail('参数错误');
        }
        
        $member_id = $this->request->memberId();
        (new GroupOrderService())->complete((int)$group_id, $member_id);
        return success('完成成功');
    }

    /**
     * 我发起的拼单
     */
    public function myCreate(): Response
    {
        $data = $this->request->params([
            ['status', ''],
            ['page', 1],
            ['limit', 10],
        ]);
        
        $service = new GroupOrderService();
        $list = $service->getMyCreate($data);
        return success($list);
    }

    /**
     * 我参与的拼单
     */
    public function myJoin(): Response
    {
        $data = $this->request->params([
            ['status', ''],
            ['page', 1],
            ['limit', 10],
        ]);
        
        $service = new GroupOrderService();
        $list = $service->getMyJoin($data);
        return success($list);
    }
}
