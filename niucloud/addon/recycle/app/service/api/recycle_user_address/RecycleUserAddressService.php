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

namespace addon\recycle\app\service\api\recycle_user_address;

use addon\recycle\app\model\recycle_user_address\RecycleUserAddress;


use core\base\BaseApiService;


/**
 * 用户退货地址服务层
 * Class RecycleUserAddressService
 * @package addon\recycle\app\service\admin\recycle_user_address
 */
class RecycleUserAddressService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleUserAddress();
    }




    /**
     * 获取用户退货地址信息
     * @param int $id
     * @return array
     */
    public function getInfo()
    {
        
        $field = 'id,member_id,address,id_card,mobile,card_pic,name,create_time,update_time,site_id';

        $info = $this->model->field($field)->where([['member_id', "=", $this->member_id],['site_id', "=", $this->site_id]])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 添加用户退货地址
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['member_id'] = $this->member_id;
        $res = $this->model->create($data);
        return $res->id;

    }

    /**
     * 用户退货地址编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        $data['member_id'] = $this->member_id;
        $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 删除用户退货地址
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->find();
        $res = $model->delete();
        return $res;
    }
  

}
