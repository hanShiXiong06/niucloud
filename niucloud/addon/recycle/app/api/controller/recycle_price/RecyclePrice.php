<?php
namespace addon\recycle\app\api\controller\recycle_price;

use app\api\controller\BaseAdminController;
use addon\recycle\app\service\api\recycle_price\RecycleCategoryService;

/**
 * 回收价格控制器
 */
class RecyclePrice extends BaseAdminController
{
    /**
     * 获取分类树
     */
    public function getTree()
    {
        return success((new RecycleCategoryService())->getTree());
    }
}