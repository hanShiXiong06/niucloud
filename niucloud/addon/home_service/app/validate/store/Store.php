<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\validate\store;

/**
 * 门店验证器
 */
class Store extends \think\Validate
{

    // 验证规则
    protected $rule = [
        'store_name' => 'require',
        'contact_name' => 'require',
        'mobile' => 'require|mobile',
        'member_id' => 'require',
        'id_card_front' => 'require',
        'id_card_back' => 'require',
        'id_number' => 'require|idCard',
        'license_img' => 'require',
        'province_id' => 'require|integer|gt:0',
        'city_id' => 'require|integer|gt:0',
        'district_id' => 'require|integer|gt:0',
        'full_address' => 'require',
        'lng' => 'require|float',
        'lat' => 'require|float',
        'service_ratio' => 'require|float|gt:0',
        'headimg' => 'max:255', // 头像为可选，但限制长度
    ];

    // 错误提示信息
    protected $message = [
        'store_name.require' => 'store.store_name_require',
        'contact_name.require' => 'store.contact_name_require',
        'mobile.require' => 'store.mobile_require',
        'mobile.mobile' => 'store.mobile_format_error',
        'member_id.require' => 'store.member_id_require',
        'id_card_front.require' => 'store.id_card_front_require',
        'id_card_back.require' => 'store.id_card_back_require',
        'id_number.require' => 'store.id_number_require',
        'id_number.idCard' => 'store.id_number_format_error',
        'license_img.require' => 'store.license_img_require',
        'province_id.require' => 'store.province_id_require',
        'province_id.integer' => 'store.province_id_format_error',
        'province_id.gt' => 'store.province_id_must_gt_zero',
        'city_id.require' => 'store.city_id_require',
        'city_id.integer' => 'store.city_id_format_error',
        'city_id.gt' => 'store.city_id_must_gt_zero',
        'district_id.require' => 'store.district_id_require',
        'district_id.integer' => 'store.district_id_format_error',
        'district_id.gt' => 'store.district_id_must_gt_zero',
        'full_address.require' => 'store.full_address_require',
        'lng.require' => 'store.lng_require',
        'lng.float' => 'store.lng_format_error',
        'lat.require' => 'store.lat_require',
        'lat.float' => 'store.lat_format_error',
        'service_ratio.require' => 'store.service_ratio_require',
        'service_ratio.float' => 'store.service_ratio_format_error',
        'service_ratio.gt' => 'store.service_ratio_must_gt_zero',
        'headimg.max' => 'store.headimg_length_error',
    ];

    // 验证场景
    protected $scene = [
        // 添加场景：验证所有必填字段
        'add' => [
            'store_name', 'contact_name', 'mobile', 'member_id',
            'id_card_front', 'id_card_back', 'id_number', 'license_img',
            'province_id', 'city_id', 'district_id', 'full_address',
            'lng', 'lat', 'service_ratio', 'headimg'
        ],
        // 编辑场景：允许部分字段更新，必选字段仍需验证
        'edit' => [
            'store_name', 'contact_name', 'mobile',
            'province_id', 'city_id', 'district_id', 'full_address',
            'lng', 'lat', 'service_ratio', 'headimg'
            // 编辑时可不需要重新验证身份证、营业执照等图片（根据实际业务调整）
        ]
    ];

}