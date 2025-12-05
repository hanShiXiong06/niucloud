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

namespace addon\ai_image\app\api\controller\aiimagecreate;

use core\base\BaseApiController;
use addon\ai_image\app\service\api\aiimagecreate\AiimageCreateService;


/**
 * 作品列控制器
 * Class AiimageCreate
 * @package addon\ai_image\app\adminapi\controller\aiimagecreate
 */
class AiimageCreate extends BaseApiController
{
   /**
    * 获取作品列列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["member_id",""],
             ["model_id",""],
             ["status",""],
             ["create_time",["",""]]
        ]);
        return success((new AiimageCreateService())->getPage($data));
    }

    /**
     * 作品列详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new AiimageCreateService())->getInfo($id));
    }

    /**
     * 添加作品列
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
             ["member_id",0],
             ["model_id",0],
             ["prompt",""],
             ["image_urls",""],
             ["aspect_ratio",""],
             ["images",""],
             ["status",0],
             ["point",0],
             ["is_self",0],
             ["msg",""],
             ["platform",""],
             ["channel",""],
             ["image_size","1K"]
        ]);
        $this->validate($data, 'addon\ai_image\app\validate\aiimagecreate\AiimageCreate.add');
        $id = (new AiimageCreateService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 作品列编辑
     * @param $id  作品列id
     * @return \think\Response
     */
    public function edit(int $id){
        $data = $this->request->params([
             ["member_id",0],
             ["model_id",0],
             ["prompt",""],
             ["image_urls",""],
             ["aspect_ratio",""],
             ["images",""],
             ["status",0],
             ["point",0],
             ["is_self",0],
             ["msg",""],
             ["platform",""],
             ["channel",""],

        ]);
        $this->validate($data, 'addon\ai_image\app\validate\aiimagecreate\AiimageCreate.edit');
        (new AiimageCreateService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 作品列删除
     * @param $id  作品列id
     * @return \think\Response
     */
    public function del(int $id){
        (new AiimageCreateService())->del($id);
        return success('DELETE_SUCCESS');
    }

    
    public function getMemberAll(){
         return success(( new AiimageCreateService())->getMemberAll());
    }

    public function getAiimageModelAll(){
         return success(( new AiimageCreateService())->getAiimageModelAll());
    }

}
