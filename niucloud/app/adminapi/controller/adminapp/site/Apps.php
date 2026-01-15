<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace app\adminapi\controller\adminapp\site;

use app\service\admin\adminapp\AppsService;
use app\service\admin\adminapp\NavService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 控制台
 */
class Apps extends BaseAdminController
{
    /**
     * 首页应用
     * @return Response
     */
    public function getAppsOfIndex()
    {
        //获取拥有权限的应用列表(结合公)
        //整合应用
        return success((new AppsService())->getAppsOfIndex([]));
    }
    /**
     * 设置首页应用
     * @return Response
     */
    public function setAppsOfIndex(){
        $data = $this->request->params([
            ['value', []],
        ]);
        //整合应用
        return success((new AppsService())->setAppsOfIndex($data['value']));
    }



    /**
     * 应用列表
     * @return Response
     */
    public function getApps()
    {
        //获取拥有权限的应用列表(结合公)
        //整合应用
       
        return success((new AppsService())->getAppsOfCheck([]));
    }

    /**
     * 获取底部导航
     * @return Response
     */
    public function getBottomNav(){
        return success((new NavService())->getNavList([]));
    }


    /**
     * 用户中心应用
     * @return Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getAppOfUserCenter(){
        return success((new AppsService())->getAppOfUserCenter([]));
    }

}
