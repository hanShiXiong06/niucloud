<?php
namespace addon\tk_jhkd\app\validate;

/**
 * 聚合快递通知列表验证器
 * Class TkjhkdNotice
 * @package app\validate\tkjhkd_notice
 */
class TkjhkdNotice extends \think\Validate
{

       protected $rule = [
            'site_id' => 'require',
            'title' => 'require',
            'image' => 'require',
            'content' => 'require',
            'status' => 'require',
            'create_time' => 'require',
            'update_time' => 'require',
        ];

       protected $message = [];

       protected $scene = [
            "add" => ['title','image','content','status'],
            "edit" => ['title','image','content','status']
        ];

}