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

namespace addon\fast_pay\app\service\admin\businessmember;

use addon\fast_pay\app\model\businessmember\BusinessMember;
use addon\fast_pay\app\model\business\Business;
use app\model\member\Member;

use core\base\BaseAdminService;


/**
 * 商户会员服务层
 * Class BusinessMemberService
 * @package addon\fast_pay\app\service\admin\businessmember
 */
class BusinessMemberService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new BusinessMember();
    }

    /**
     * 获取商户会员列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,business_id,member_id,level,balance,create_time,update_time,delete_time';
        $order = 'create_time desc';

        $search_model = $this->model->where([ [ 'site_id' ,"=", $this->site_id ] ])->withSearch(["business_id","member_id","level","balance","create_time"], $where)->with(['business','member'])->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取商户会员信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,business_id,member_id,level,balance,create_time,update_time,delete_time';

        $info = $this->model->field($field)->where([['id', "=", $id]])->with(['business','member'])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 添加商户会员
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $res = $this->model->create($data);
        return $res->id;

    }

    /**
     * 商户会员编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {

        $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 删除商户会员
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->find();
        $res = $model->delete();
        return $res;
    }
    
    public function getBusinessAll(){
       $businessModel = new Business();
       return $businessModel->where([["site_id","=",$this->site_id]])->select()->toArray();
    }

    public function getMemberAll(){
       $memberModel = new Member();
       return $memberModel->where([["site_id","=",$this->site_id]])->select()->toArray();
    }

}
