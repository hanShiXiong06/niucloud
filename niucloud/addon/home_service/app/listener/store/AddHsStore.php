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


namespace addon\home_service\app\listener\store;

use addon\home_service\app\dict\store\StoreDict;
use addon\home_service\app\service\core\store\CoreStoreApplicationService;

/**
 * 添加门店 后续业务
 * Class MemberAccount
 * @package app\listener\member
 */
class AddHsStore
{

    /**
     *
     */
    public function handle(array $data)
    {
        //后台添加才触发
        if (isset($data['is_default']) && $data['is_default'] == 1  &&  !isset($data['source'] )  ) {
            // 定义需要的字段及默认值
            $defaultData = [
                "store_name" => "",
                "contact_name" => "",
                "headimg" => "",
                "id_card_front" => "",
                "id_card_back" => "",
                "id_number" => "",
                "mobile" => "",
                "license_img" => "",
                "apply_desc" => "",
                "province_id" => 0,
                "city_id" => 0,
                "district_id" => 0,
                "full_address" => "",
                "lng" => "",
                "lat" => "",
                "site_id" => 0, // 从当前上下文获取
                "member_id" => 0, // 从当前上下文获取
                "source" => 'member', // 固定默认值
                "audit_status" => StoreDict::PASS, // 固定默认值
                "audit_time" => time(), // 固定默认值
            ];
            // 合并数据：$data 中的字段覆盖默认值，缺失的字段使用默认值
            $apply_data = array_merge($defaultData, array_intersect_key($data, $defaultData));
            // 调用服务层方法
            return (new CoreStoreApplicationService())->apply($apply_data);
        } else {
            return true;
        }
    }

}
