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

namespace addon\ai_image\app\adminapi\controller\aiimagepackage;

use core\base\BaseAdminController;
use addon\ai_image\app\service\admin\aiimagepackage\AiimagePackageService;


/**
 * 套餐列控制器
 * Class AiimagePackage
 * @package addon\ai_image\app\adminapi\controller\aiimagepackage
 */
class AiimagePackage extends BaseAdminController
{
   /**
    * 获取套餐列列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["name",""],
             ["type",""],
             ["status",""]
        ]);
        return success((new AiimagePackageService())->getPage($data));
    }

    /**
     * 套餐列详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new AiimagePackageService())->getInfo($id));
    }

    /**
     * 添加套餐列
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
             ["name",""],
             ["image",""],
             ["price",0.00],
             ["point",0],
             ["num",0],
             ["type",""],
             ["day",0],
             ["limit",0],
             ["status",0],
             ["sort",0],

        ]);
        $this->validate($data, 'addon\ai_image\app\validate\aiimagepackage\AiimagePackage.add');
        $id = (new AiimagePackageService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 套餐列编辑
     * @param $id  套餐列id
     * @return \think\Response
     */
    public function edit(int $id){
        $data = $this->request->params([
             ["name",""],
             ["image",""],
             ["price",0.00],
             ["point",0],
             ["num",0],
             ["type",""],
             ["day",0],
             ["limit",0],
             ["status",0],
             ["sort",0],

        ]);
        $this->validate($data, 'addon\ai_image\app\validate\aiimagepackage\AiimagePackage.edit');
        (new AiimagePackageService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 套餐列删除
     * @param $id  套餐列id
     * @return \think\Response
     */
    public function del(int $id){
        (new AiimagePackageService())->del($id);
        return success('DELETE_SUCCESS');
    }

    
}
