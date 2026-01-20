<?php
// +----------------------------------------------------------------------
// | Author: addon888
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\model\shop_address;

use app\model\sys\SysArea;
use core\base\BaseModel;

/**
 * 商家地址库模型
 * Class ShopAddress
 * @package addon\shop\app\model\shop_address
 */
class ShopAddress extends BaseModel
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
    protected $name = 'shop_address';

    /**
     * 搜索器:商家地址库手机号
     * @param $value
     * @param $data
     */
    public function searchMobileAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("mobile", "like", "%$value%");
        }
    }

    /**
     * 搜索器:商家地址库地址
     * @param $value
     * @param $data
     */
    public function searchFullAddressAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("full_address", "like", "%$value%");
        }
    }
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
