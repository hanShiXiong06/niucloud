<?php

namespace addon\tk_jhkd\app\model;
use app\model\sys\SysArea;
use core\base\BaseModel;

/**
 * 会员收货地址模型
 * Class MemberAddress
 * @package app\model\member
 */
class MemberAddress extends BaseModel
{

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'member_address';

    public function getProvinceNameAttr($value, $data)
    {
        if(isset($data['province_id'])&&$data['province_id']=='') return '';
        $res=(new SysArea())->where(['id'=>$data['province_id']])->findOrEmpty();
        return $res['name'];
    }
    public function getCityNameAttr($value, $data)
    {
        if(isset($data['city_id'])&&$data['city_id']=='') return '';
        $res=(new SysArea())->where(['id'=>$data['city_id']])->findOrEmpty();
        return $res['name'];
    }
    public function getDistrictNameAttr($value, $data)
    {
        if(isset($data['district_id'])&&$data['district_id']=='') return '';
        $res=(new SysArea())->where(['id'=>$data['district_id']])->findOrEmpty();
        return $res['name'];
    }
}
