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
use app\service\core\member\CoreMemberConfigService;
use app\service\core\member\CoreMemberService;
use core\base\BaseAdminService;

/**
 * 应用
 * Class MemberConfigService
 * @package app\service\admin\member
 */
class AppsService extends BaseAdminService
{




    public function getApps(array $param = []){

        $apps = (new CoreAppService())->getMobileApp();

        if (AuthService::isSuperAdmin()) {
            $is_admin = 1;
        } else {
            $auth_service = new AuthService();
            $user_role_info = $auth_service->getAuthRole($this->site_id);
            if(empty($user_role_info))
                return [];
            $is_admin = $user_role_info['is_admin'];//是否是超级管理员组

        }

        $menu_service = new MenuService();

        if($is_admin){//查询全部启用的权限
            $menu_list =  $menu_service->getSiteAllMenuList(AppTypeDict::SITE, 1, 0, 1, 1);
        }else{
            $user_role_ids = $user_role_info['role_ids'];
            $role_service = new RoleService();
            $menu_keys = $role_service->getMenuIdsByRoleIds($this->site_id, $user_role_ids);
            $menu_list =  $menu_service->getSiteMenuListByMenuKeys($this->site_id, $menu_keys, AppTypeDict::SITE, 0, 'all', 1, 1);
        }
        $menu_keys  = array_column($menu_list, 'menu_key');
        foreach($apps as $k => $v){
            $item_menu_key = $v['menu_key'] ?? '';
            if(!empty($item_menu_key) && !in_array($item_menu_key, $menu_keys)){
                unset($apps[$k]);
            }
        }
        $apps = array_filter($apps);

        return  $apps;

    }

    public function getAppsOfIndex(array $param = []){
        //todo  后续save
        $apps = $this->getApps();

        $keys = (new CoreAdminAppService())->getValue($this->uid, $this->site_id, 'app');

        $app_limit = env('system.todo_limit', 9);
        if(empty($keys)){
            $apps_of_index = array_slice($apps, 0, $app_limit);
        }else{
            $apps_of_index = [];
            foreach($apps as $item){
                if(in_array($item['key'], $keys)){
                    $item['sort'] = array_search($item['key'], $keys);
                    $apps_of_index[] = $item;
                }
            }
        }

        usort($apps_of_index, function($app_a, $app_b) {
            return $app_a['sort'] <=> $app_b['sort'];
        });
        return $apps_of_index;
    }


    /**
     * 设置待办项
     * @param array $param
     * @return true
     */
    public function setAppsOfIndex(array $param = []){
        (new CoreAdminAppService())->setValue($this->uid, $this->site_id, 'app', $param);
        return true;
    }
    /**
     * 应用
     * @return array|null
     */
    public function getAppsOfCheck(){
        $apps = $this->getApps();
        $app_groups = (new CoreAppService())->getMobileAppGroup();

        usort($app_groups, function($app_groups_a, $app_groups_b) {
            return $app_groups_a['sort'] <=> $app_groups_b['sort'];
        });

        usort($apps, function($app_a, $app_b) {
            return $app_a['sort'] <=> $app_b['sort'];
        });
        if(!empty($apps)){
            foreach($apps  as $app){
                $group = $app['group'] ?? '';
                foreach($app_groups as &$app_group){{
                    if($group == $app_group['key']){
                        $app_group['childs'][] = $app;
                        break;
                    }
                }

                }
            }
        }
        return $app_groups;

    }

    /**
     *
     * @return array|array[]
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getAppOfUserCenter(){
        $apps = [
            [
                'name' => '商家信息',
                'key' => 'site_detail',
                'group' => 'site',
                'menu_key' => 'shop_setting_index',
                'sort' => 2,
                'page' => '/app/pages/site/info',
                'icon' => '/addon/mall/site/menu/site_detail.png'
            ],
            [
                'name' => '客户管理',
                'key' => 'member_manage',
                'group' => 'member',
                'menu_key' => 'shop_member_list',
                'sort' => 1,
                'page' => '/app/pages/member/index',
                'icon' => '/addon/mall/site/menu/member.png'
            ],
        ];

        if (AuthService::isSuperAdmin()) {
            $is_admin = 1;
        } else {
            $auth_service = new AuthService();
            $user_role_info = $auth_service->getAuthRole($this->site_id);
            if(empty($user_role_info))
                return [];
            $is_admin = $user_role_info['is_admin'];//是否是超级管理员组

        }

        $menu_service = new MenuService();

        if($is_admin){//查询全部启用的权限
            $menu_list =  $menu_service->getSiteAllMenuList(AppTypeDict::SITE, 1, 0, 1, 1);
        }else{
            $user_role_ids = $user_role_info['role_ids'];
            $role_service = new RoleService();
            $menu_keys = $role_service->getMenuIdsByRoleIds($this->site_id, $user_role_ids);
            $menu_list =  $menu_service->getSiteMenuListByMenuKeys($this->site_id, $menu_keys, AppTypeDict::SITE, 0, 'all', 1, 1);
        }
        $menu_keys  = array_column($menu_list, 'menu_key');
        foreach($apps as $k => $v){
            $item_menu_key = $v['menu_key'] ?? '';
            if(!empty($item_menu_key) && !in_array($item_menu_key, $menu_keys)){
                unset($apps[$k]);
            }
        }
        $apps = array_filter($apps);

        return  $apps;
    }

}