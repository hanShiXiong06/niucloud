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

namespace addon\phone_shop\app\adminapi\controller\site;

use core\base\BaseAdminController;
use addon\phone_shop\app\service\admin\site\SiteService;


/**
 * 站点(二手)管理控制器
 * Class Site
 * @package addon\phone_shop\app\adminapi\controller\site
 */
class Site extends BaseAdminController
{
   /**
    * 获取站点(二手)管理列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["site_name",""],
             ["client",""]
        ]);
        return success((new SiteService())->getPage($data));
    }

    /**
     * 站点(二手)管理详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new SiteService())->getInfo($id));
    }

    /**
     * 添加站点(二手)管理
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
            ["site_id",""],
             ["site_name",""],
             ["client",0],
             ["category_status",0],
             ["brand_status",0],
             ["label_group_status",0],
             ["label_status",0],
             ["service_status",0],
             ["price_status",0]
        ]);
        $this->validate($data, 'addon\phone_shop\app\validate\site\Site.add');
        $id = (new SiteService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 站点(二手)管理编辑
     * @param $id  站点(二手)管理id
     * @return \think\Response
     */
    public function edit(int $id){
        $data = $this->request->params([
             ["site_name",""],
             ["site_id",""],
             ["client",0],
             ["category_status",0],
             ["brand_status",0],
             ["label_group_status",0],
             ["label_status",0],
             ["service_status",0],
            ["price_status",0]
        ]);
        // $this->validate($data, 'addon\phone_shop\app\validate\site\Site.edit');
        (new SiteService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 站点(二手)管理删除
     * @param $id  站点(二手)管理id
     * @return \think\Response
     */
    public function del(int $id){
        (new SiteService())->del($id);
        return success('DELETE_SUCCESS');
    }

    
}
