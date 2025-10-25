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

namespace addon\recycle\app\service\core\RecyclePrice;

use addon\recycle\app\model\RecyclePrice;
use core\base\BaseCoreService;

class CoreRecyclePriceService extends BaseCoreService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecyclePrice();
    }
    
    public function getTree($condition=[]){
        $field = 'id,name,parent_id';
        $order = 'id desc';
        $list = $this->model->where($condition)->field($field)->order($order)->select()->toArray();
        $list = list_to_tree($list, 'id', 'parent_id', 'child_list');
        $this->sortArrayById($list);
        
        return $list;
    }

    public function sortArrayById(&$array) {
        // 首先对当前层级的数组按 id 进行排序
        usort($array, function ($a, $b) {
            return $a['id'] <=> $b['id']; // 使用太空船操作符进行数字比较
        });
        
        // 递归排序每个子数组
        foreach ($array as &$item) {
            if (isset($item['child_list']) && is_array($item['child_list'])) {
                $this->sortArrayById($item['child_list']); // 递归调用
             
            }
            unset($item['parent_id']);
        }
    }
}
