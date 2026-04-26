<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\recycle\app\adminapi\controller\recycle_category;

use addon\recycle\app\service\admin\recycle_category\RecycleCategoryConfigService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 回收分类配置控制器
 * Class RecycleCategoryConfig
 * @package addon\recycle\app\adminapi\controller\recycle_category
 */
class RecycleCategoryConfig extends BaseAdminController
{
   
    /**
     * 获取配置
     * @return Response
     */
    public function getConfig()
    {
    
        return success((new RecycleCategoryConfigService())->getConfig());
    }

    /**
     * 设置配置
     * @return Response
     */
    public function setConfig()
    {
        $res = (new RecycleCategoryConfigService())->setConfig();
        return success('EDIT_SUCCESS', $res);
    }
}
