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

namespace app\service\admin\adminapp;

use app\service\core\adminapp\CoreAdminAppService;
use app\service\core\adminapp\CoreIndexService;
use core\base\BaseAdminService;

/**
 * 待办
 * Class StatService
 * @package app\service\admin\adminapp
 */
class TodoService extends BaseAdminService
{


    /**
     * 待办项
     * @param array $param
     * @return void
     */
    public function getTodoList(array $param = []){
        $list = (new CoreIndexService())->getTodoList();
        //查询当前配置的
        $keys = (new CoreAdminAppService())->getValue($this->uid, $this->site_id, 'todo');
        $todo_limit = env('system.todo_limit', 4);
        if(empty($keys)){
            $todo_list = array_slice($list, 0, $todo_limit);
        }else{
            $todo_list = [];
            foreach($list as $item){
                if(in_array($item['key'], $keys)){
                    $item['sort'] = array_search($item['key'], $keys);
                    $todo_list[] = $item;
                }
            }
        }
        usort($todo_list, function($list_a, $list_b) {
            return $list_a['sort'] <=> $list_b['sort'];
        });
        return $todo_list;

    }

    /**
     * 设置待办项
     * @param array $param
     * @return true
     */
    public function setTodoList(array $param = []){
        (new CoreAdminAppService())->setValue($this->uid, $this->site_id, 'todo', $param);
        return true;
    }
    public function getAllTodoList(array $param = []){
        $list = (new CoreIndexService())->getTodoList();
        usort($list, function($list_a, $list_b) {
            return $list_a['sort'] <=> $list_b['sort'];
        });
        return $list;
    }
}