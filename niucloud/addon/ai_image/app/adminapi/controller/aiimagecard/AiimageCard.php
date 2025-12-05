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

namespace addon\ai_image\app\adminapi\controller\aiimagecard;

use app\service\admin\member\MemberService;
use core\base\BaseAdminController;
use addon\ai_image\app\service\admin\aiimagecard\AiimageCardService;


/**
 * 卡密兑换控制器
 * Class AiimageCard
 * @package addon\ai_image\app\adminapi\controller\aiimagecard
 */
class AiimageCard extends BaseAdminController
{
   /**
    * 获取卡密兑换列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["card_num",""],
             ["is_use",""],
             ["is_export",""],
             ["pid",""]
        ]);
        return success((new AiimageCardService())->getPage($data));
    }

    /**
     * 卡密兑换详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new AiimageCardService())->getInfo($id));
    }

    /**
     * 添加卡密兑换
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
             ["member_id",0],
             ["num",""],
             ["point",0],
             ["is_use",0],
             ["use_time",0],
             ["is_export",0],
             ["pid",0],
             ["expire_time",0],

        ]);
        $this->validate($data, 'addon\ai_image\app\validate\aiimagecard\AiimageCard.add');
        if ($data['expire_time'] != 0) {
            $data['expire_time'] = strtotime($data['expire_time']);
        }
        $id = (new AiimageCardService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 卡密兑换编辑
     * @param $id  卡密兑换id
     * @return \think\Response
     */
    public function edit(int $id){
        $data = $this->request->params([
             ["member_id",0],
             ["card_num",""],
             ["point",0],
             ["is_use",0],
             ["use_time",0],
             ["is_export",0],
             ["pid",0],
             ["expire_time",0],

        ]);
        $this->validate($data, 'addon\ai_image\app\validate\aiimagecard\AiimageCard.edit');
        (new AiimageCardService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 卡密兑换删除
     * @param $id  卡密兑换id
     * @return \think\Response
     */
    public function del(int $id){
        (new AiimageCardService())->del($id);
        return success('DELETE_SUCCESS');
    }

    
    public function getMemberAll(){
        $data = $this->request->params([
            ['keyword', ''],
            ['register_type', ''],
            ['register_channel', ''],
            ['create_time', []],
            ['member_label', 0],
            ['member_level', 0],
        ]);
        return success((new MemberService())->getPage($data));
    }
    public function delselect()
    {
        $data = $this->request->post();
        (new  AiimageCardService())->delselect($data);
        return success('DELETE_SUCCESS');
    }
}
