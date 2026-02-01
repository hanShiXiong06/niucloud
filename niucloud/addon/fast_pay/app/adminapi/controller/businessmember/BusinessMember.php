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

namespace addon\fast_pay\app\adminapi\controller\businessmember;

use core\base\BaseAdminController;
use addon\fast_pay\app\service\admin\businessmember\BusinessMemberService;


/**
 * 商户会员控制器
 * Class BusinessMember
 * @package addon\fast_pay\app\adminapi\controller\businessmember
 */
class BusinessMember extends BaseAdminController
{
   /**
    * 获取商户会员列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["business_id",""],
             ["member_id",""],
             ["level",""],
             ["balance",""],
             ["create_time",["",""]]
        ]);
        return success((new BusinessMemberService())->getPage($data));
    }

    /**
     * 商户会员详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new BusinessMemberService())->getInfo($id));
    }

    /**
     * 添加商户会员
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
             ["business_id",0],
             ["member_id",""],
             ["level",""],
             ["balance",""],

        ]);
        $this->validate($data, 'addon\fast_pay\app\validate\businessmember\BusinessMember.add');
        $id = (new BusinessMemberService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 商户会员编辑
     * @param $id  商户会员id
     * @return \think\Response
     */
    public function edit(int $id){
        $data = $this->request->params([
             ["business_id",0],
             ["member_id",""],
             ["level",""],
             ["balance",""],

        ]);
        $this->validate($data, 'addon\fast_pay\app\validate\businessmember\BusinessMember.edit');
        (new BusinessMemberService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 商户会员删除
     * @param $id  商户会员id
     * @return \think\Response
     */
    public function del(int $id){
        (new BusinessMemberService())->del($id);
        return success('DELETE_SUCCESS');
    }

    
    public function getBusinessAll(){
         return success(( new BusinessMemberService())->getBusinessAll());
    }

    public function getMemberAll(){
         return success(( new BusinessMemberService())->getMemberAll());
    }

}
