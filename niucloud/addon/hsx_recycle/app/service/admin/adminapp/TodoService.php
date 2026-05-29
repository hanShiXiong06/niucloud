<?php

namespace addon\hsx_recycle\app\service\admin\adminapp;

use addon\hsx_recycle\app\service\core\adminapp\CoreAdminAppService;
use addon\hsx_recycle\app\service\core\adminapp\CoreIndexService;
use core\base\BaseAdminService;

/**
 * 手机管理端待办设置。
 */
class TodoService extends BaseAdminService
{
    public function getTodoList(array $param = [])
    {
        $list = (new CoreIndexService())->getTodoList();
        $keys = (new CoreAdminAppService())->getValue($this->uid, $this->site_id, 'todo');

        $todo_limit = env('system.todo_limit', 4);
        if (empty($keys)) {
            $todo_list = array_slice($list, 0, $todo_limit);
        } else {
            $todo_list = [];
            foreach ($list as $item) {
                if (in_array($item['key'], $keys)) {
                    $item['sort'] = array_search($item['key'], $keys);
                    $todo_list[] = $item;
                }
            }
        }

        usort($todo_list, function ($list_a, $list_b) {
            return $list_a['sort'] <=> $list_b['sort'];
        });

        return $todo_list;
    }

    public function setTodoList(array $param = [])
    {
        (new CoreAdminAppService())->setValue($this->uid, $this->site_id, 'todo', $param);
        return true;
    }

    public function getAllTodoList(array $param = [])
    {
        $list = (new CoreIndexService())->getTodoList();
        usort($list, function ($list_a, $list_b) {
            return $list_a['sort'] <=> $list_b['sort'];
        });
        return $list;
    }
}
