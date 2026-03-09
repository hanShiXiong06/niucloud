<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\service\core\GroupOrderService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 拼单好饭管理控制器
 */
class GroupOrder extends BaseAdminController
{
    /**
     * 获取拼单列表
     */
    public function lists(): Response
    {
        $data = $this->request->params([
            ['group_type', ''],
            ['status', ''],
            ['school_id', ''],
            ['campus', ''],
            ['keyword', ''],
            ['page', 1],
            ['limit', 10],
        ]);
        
        $list = (new GroupOrderService())->getPage($data);
        return success($list);
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
     * 获取拼单类型列表
     */
    public function typeList(): Response
    {
        $list = (new GroupOrderService())->getTypeList();
        return success($list);
    }

    /**
     * 获取拼单状态列表
     */
    public function statusList(): Response
    {
        $list = (new GroupOrderService())->getStatusList();
        return success($list);
    }
}
