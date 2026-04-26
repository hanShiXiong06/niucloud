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

namespace addon\wj_books\app\validate\wj_books_config;

use think\Validate;

/**
 * 旧书回收系统配置验证器
 * Class WjBooksConfig
 * @package addon\wj_books\app\validate\wj_books_config
 */
class WjBooksConfig extends Validate
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule = [
        'platform_name' => 'max:100',
        'min_book_count' => 'integer|between:1,100',
        'rejected_book_retrieve_days' => 'integer|between:1,30',
        'price_adjust_rate' => 'float|between:-100,1000',
        'recycle_notice_content' => 'max:5000',
        'book_api_enabled' => 'in:0,1',
        'book_api_provider' => 'in:0,1',
        'book_api_key' => 'max:255',
        'yunyang_appid' => 'max:100',
        'yunyang_app_secret' => 'max:255',
        'yunyang_channel_subtag' => 'max:50',
        'yunyang_auto_order' => 'in:0,1',
        'express_receiver_name' => 'max:50',
        'express_receiver_mobile' => 'mobile',
        'express_receiver_province' => 'max:50',
        'express_receiver_city' => 'max:50',
        'express_receiver_county' => 'max:50',
        'express_receiver_town' => 'max:50',
        'express_receiver_location' => 'max:255',
    ];

    /**
     * 错误信息
     * @var array
     */
    protected $message = [
        'platform_name.require' => '平台名称不能为空',
        'platform_name.max' => '平台名称最多不能超过100个字符',
        'min_book_count.require' => '上门回收最低图书数量不能为空',
        'min_book_count.integer' => '上门回收最低图书数量必须为整数',
        'min_book_count.between' => '上门回收最低图书数量必须在1-100之间',
        'rejected_book_retrieve_days.require' => '拒收书籍可取回期限不能为空',
        'rejected_book_retrieve_days.integer' => '拒收书籍可取回期限必须为整数',
        'rejected_book_retrieve_days.between' => '拒收书籍可取回期限必须在1-30之间',
        'price_adjust_rate.float' => '回收价调整比例必须是数字',
        'price_adjust_rate.between' => '回收价调整比例必须在-100到1000之间',
        'recycle_notice_content.max' => '订单回收须知最多不能超过5000个字符',
        'book_api_enabled.require' => '是否启用图书API不能为空',
        'book_api_enabled.in' => '是否启用图书API参数错误',
        'book_api_provider.requireIf' => '图书数据API来源不能为空',
        'book_api_provider.in' => '图书数据API来源参数错误',
        'book_api_key.requireIf' => '图书API密钥不能为空',
        'yunyang_appid.require' => '云洋物流AppID不能为空',
        'yunyang_app_secret.require' => '云洋物流AppSecret不能为空',
        'yunyang_channel_subtag.require' => '指定快递类型不能为空',
        'express_receiver_name.require' => '收件人姓名不能为空',
        'express_receiver_name.max' => '收件人姓名最多不能超过50个字符',
        'express_receiver_mobile.require' => '收件人电话不能为空',
        'express_receiver_mobile.mobile' => '收件人电话格式不正确',
        'express_receiver_province.require' => '收件省份不能为空',
        'express_receiver_province.max' => '收件省份最多不能超过50个字符',
        'express_receiver_city.require' => '收件城市不能为空',
        'express_receiver_city.max' => '收件城市最多不能超过50个字符',
        'express_receiver_county.require' => '收件区县不能为空',
        'express_receiver_county.max' => '收件区县最多不能超过50个字符',
        'express_receiver_location.require' => '收件详细地址不能为空',
        'express_receiver_location.max' => '收件详细地址最多不能超过255个字符',
    ];

    /**
     * 验证场景
     * @var array
     */
    protected $scene = [
        'update' => [
            'platform_name', 'min_book_count', 'rejected_book_retrieve_days', 'price_adjust_rate', 'recycle_notice_content', 'book_api_enabled',
            'book_api_provider', 'book_api_key', 'yunyang_appid', 'yunyang_app_secret',
            'yunyang_channel_subtag', 'express_receiver_name', 'express_receiver_mobile',
            'express_receiver_province', 'express_receiver_city', 'express_receiver_county',
            'express_receiver_location'
        ],
    ];
} 
