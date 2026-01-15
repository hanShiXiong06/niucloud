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

use app\dict\sys\AppTypeDict;
use app\service\admin\auth\AuthService;
use app\service\admin\sys\MenuService;
use app\service\admin\sys\RoleService;
use app\service\core\adminapp\CoreAdminAppService;
use app\service\core\adminapp\CoreAppService;
use app\service\core\adminapp\CoreIndexService;
use core\base\BaseAdminService;

/**
 * 统计设置
 * Class StatService
 * @package app\service\admin\adminapp
 */
class StatService extends BaseAdminService
{


    /**
     * 统计项
     * @param array $param
     * @return array|null
     */
    public function getStatList(array $param = []){
        $list = (new CoreIndexService())->getStatList();
        //查询当前配置的
        $keys = (new CoreAdminAppService())->getValue($this->uid, $this->site_id, 'stat');
        $stat_limit = env('system.stat_limit', 6);
        if(empty($keys)){
            $stat_list = array_slice($list, 0, $stat_limit);

        }else{
            $stat_list = [];
            foreach($list as $item){
                if(in_array($item['key'], $keys)){
                    $item['sort'] = array_search($item['key'], $keys);
                    $stat_list[] = $item;
                }
            }
        }
        usort($stat_list, function($list_a, $list_b) {
            return $list_a['sort'] <=> $list_b['sort'];
        });
        return $stat_list;

    }

    /**
     * 设置统计项
     * @param array $param
     * @return true
     */
    public function setStatList(array $param = []){
        (new CoreAdminAppService())->setValue($this->uid, $this->site_id, 'stat', $param);
        return true;
    }

    public function getAllStatList(array $param = []){
        $list = (new CoreIndexService())->getStatList();
        usort($list, function($list_a, $list_b) {
            return $list_a['sort'] <=> $list_b['sort'];
        });
        return $list;

    }

}