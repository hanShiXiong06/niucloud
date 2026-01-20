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

namespace addon\kd_api\app\validate\kdapi_api;
use core\base\BaseValidate;
/**
 * api对接验证器
 * Class KdapiApi
 * @package addon\kd_api\app\validate\kdapi_api
 */
class KdapiApi extends BaseValidate
{

       protected $rule = [
            'rate' => 'require'
        ];

       protected $message = [
            'rate.require' => ['common_validate.require', ['rate']]
        ];

       protected $scene = [
            "add" => ['member_id', 'rate', 'api_key', 'api_secret', 'status', 'qps', 'limit', 'num', 'commission'],
            "edit" => ['member_id', 'rate', 'api_key', 'api_secret', 'status', 'qps', 'limit', 'num', 'commission']
        ];

}
