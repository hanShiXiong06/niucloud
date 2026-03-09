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
}
