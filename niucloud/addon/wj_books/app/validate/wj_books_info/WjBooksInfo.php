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

namespace addon\wj_books\app\validate\wj_books_info;
use core\base\BaseValidate;
/**
 * 图书信息验证器
 * Class WjBooksInfo
 * @package addon\wj_books\app\validate\wj_books_info
 */
class WjBooksInfo extends BaseValidate
{

       protected $rule = [
            'recycle_count' => 'between:0,9999',
        ];

       protected $message = [
            'recycle_count.between' => ['common_validate.between', ['recycle_count','0','9999']],
        ];

       protected $scene = [
            "add" => ['isbn', 'isbn10', 'title', 'author', 'publisher', 'pub_date', 'price', 'binding', 'page', 'edition', 'img', 'small_img', 'gist', 'recycle_price', 'can_recycle', 'recycle_count', 'api_json', 'api_query_time'],
            "edit" => ['isbn', 'isbn10', 'title', 'author', 'publisher', 'pub_date', 'price', 'binding', 'page', 'edition', 'img', 'small_img', 'gist', 'recycle_price', 'can_recycle', 'recycle_count', 'api_json', 'api_query_time']
        ];

}
