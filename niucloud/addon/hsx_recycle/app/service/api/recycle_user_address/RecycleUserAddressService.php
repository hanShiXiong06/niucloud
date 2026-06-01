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

namespace addon\hsx_recycle\app\service\api\recycle_user_address;

use addon\hsx_recycle\app\model\address\RecycleUserAddress;
use app\model\member\Member;


use core\base\BaseApiService;


/**
 * 用户退货地址服务层
 * Class RecycleUserAddressService
 * @package addon\hsx_recycle\app\service\admin\recycle_user_address
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
        
        $field = 'id,member_id,address,id_card,mobile,card_pic,name,province_id,city_id,district_id,province_name,city_name,district_name,detail_address,create_time,update_time,site_id';

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
        $data = $this->sanitizeAddressData($data);
        $data['site_id'] = $this->site_id;
        $data['member_id'] = $this->member_id;
        $res = $this->model->create($data);
        // 获取用户的 name 并同步到 member 表中的 nick_name 

        (new Member()) ->where([['member_id', '=', $res->member_id],['site_id', '=', $this->site_id]])->update(['nickname' => $data['name'],'mobile' => $data['mobile']]);
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
        $data = $this->sanitizeAddressData($data);
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

    private function sanitizeAddressData(array $data): array
    {
        $allowFields = [
            'member_id',
            'address',
            'id_card',
            'mobile',
            'card_pic',
            'name',
            'province_id',
            'city_id',
            'district_id',
            'province_name',
            'city_name',
            'district_name',
            'detail_address',
        ];
        $data = array_intersect_key($data, array_flip($allowFields));

        foreach (['address', 'id_card', 'mobile', 'card_pic', 'name', 'province_name', 'city_name', 'district_name', 'detail_address'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = trim((string)$data[$field]);
            }
        }
        foreach (['province_id', 'city_id', 'district_id', 'member_id'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (int)$data[$field];
            }
        }

        return $data;
    }
  

}
