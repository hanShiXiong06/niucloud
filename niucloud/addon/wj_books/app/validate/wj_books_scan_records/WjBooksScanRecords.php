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

namespace addon\wj_books\app\validate\wj_books_scan_records;
use core\base\BaseValidate;
/**
 * 图书扫描记录验证器
 * Class WjBooksScanRecords
 * @package addon\wj_books\app\validate\wj_books_scan_records
 */
class WjBooksScanRecords extends BaseValidate
{

       protected $rule = [
            'member_id' => 'require',
            'isbn' => 'require',
        ];

       protected $message = [
            'member_id.require' => ['common_validate.require', ['member_id']],
            'isbn.require' => ['common_validate.require', ['isbn']],
        ];

       protected $scene = [
            "add" => ['member_id', 'isbn', 'scan_time', 'scan_type', 'status'],
            "edit" => ['member_id', 'isbn', 'scan_time', 'scan_type', 'status']
        ];

}
