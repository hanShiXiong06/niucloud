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

namespace addon\ai_image\app\api\controller\aiimagehelp;

use core\base\BaseAdminController;
use addon\ai_image\app\service\admin\aiimagehelp\AiimageHelpService;


/**
 * 帮助中心控制器
 * Class AiimageHelp
 * @package addon\ai_image\app\adminapi\controller\aiimagehelp
 */
class AiimageHelp extends BaseAdminController
{
   /**
    * 获取帮助中心列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["title",""]
        ]);
        return success((new AiimageHelpService())->getPage($data));
    }

    /**
     * 帮助中心详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new AiimageHelpService())->getInfo($id));
    }

    
}
