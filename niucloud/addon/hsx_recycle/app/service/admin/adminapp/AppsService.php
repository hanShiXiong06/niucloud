<?php

namespace addon\hsx_recycle\app\service\admin\adminapp;

use addon\hsx_recycle\app\service\core\adminapp\CoreAdminAppService;
use addon\hsx_recycle\app\service\core\adminapp\CoreAppService;
use app\dict\sys\AppTypeDict;
use app\service\admin\auth\AuthService;
use app\service\admin\sys\MenuService;
use app\service\admin\sys\RoleService;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 手机管理端应用。
 */
class AppsService extends BaseAdminService
{
    public function getApps(array $param = [])
    {
        $apps = (new CoreAppService())->getMobileApp();
        $menu_keys = $this->getCurrentUserMenuKeys();

        foreach ($apps as $key => $item) {
            $item_menu_key = $item['menu_key'] ?? '';
            if (!empty($item_menu_key) && !in_array($item_menu_key, $menu_keys, true)) {
                unset($apps[$key]);
            }
        }

        $apps = array_values(array_filter($apps));
        // 必须先排序再选取首页默认入口，不能让插件字典的加载顺序决定展示范围。
        usort($apps, function ($app_a, $app_b) {
            return ($app_a['sort'] ?? 0) <=> ($app_b['sort'] ?? 0);
        });

        return $apps;
    }

    public function getAppsOfIndex(array $param = [])
    {
        $apps = $this->getApps();
        $keys = (new CoreAdminAppService())->getValue($this->uid, $this->site_id, 'app');

        $keys = is_array($keys) ? array_values(array_unique(array_filter($keys, function ($key) {
            return is_string($key) && $key !== '';
        }))) : [];

        $apps_of_index = [];
        foreach ($apps as $item) {
            $position = array_search($item['key'], $keys, true);
            if ($position !== false) {
                $item['sort'] = $position;
                $apps_of_index[] = $item;
            }
        }

        // 权限收回、插件卸载或错误配置导致所有选项失效时，展示有权限的默认入口。
        // 只做读取兜底，不回写用户配置；仍有效的个性化选择及顺序保持不变。
        if (empty($apps_of_index)) {
            $app_limit = (int) env('system.todo_limit', 9);
            return array_slice($apps, 0, $app_limit > 0 ? $app_limit : 9);
        }

        usort($apps_of_index, function ($app_a, $app_b) {
            return $app_a['sort'] <=> $app_b['sort'];
        });

        return $apps_of_index;
    }

    public function setAppsOfIndex(array $param = [])
    {
        $allowed_keys = array_column($this->getApps(), 'key');
        $keys = [];
        foreach ($param as $key) {
            if (!is_string($key) || !in_array($key, $allowed_keys, true)) {
                throw new CommonException('快捷入口不存在或您暂无访问权限，请刷新应用列表后重新选择');
            }
            if (!in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        // 保存移动入口 key，而非 menu_key；空数组沿用恢复默认入口的约定。
        (new CoreAdminAppService())->setValue($this->uid, $this->site_id, 'app', $keys);
        return true;
    }

    public function getAppsOfCheck()
    {
        $apps = $this->getApps();
        $app_groups = (new CoreAppService())->getMobileAppGroup();

        usort($app_groups, function ($app_groups_a, $app_groups_b) {
            return $app_groups_a['sort'] <=> $app_groups_b['sort'];
        });

        foreach ($app_groups as &$app_group) {
            if (!isset($app_group['childs'])) {
                $app_group['childs'] = [];
            }
        }

        foreach ($apps as $app) {
            $group = $app['group'] ?? '';
            foreach ($app_groups as &$app_group) {
                if ($group == $app_group['key']) {
                    $app_group['childs'][] = $app;
                    break;
                }
            }
        }

        return $app_groups;
    }

    public function getAppOfUserCenter()
    {
        $apps = [
            [
                'name' => '商家信息',
                'key' => 'site_detail',
                'group' => 'site',
                'menu_key' => 'shop_setting_index',
                'sort' => 2,
                'page' => '/app/pages/site/info',
                'icon' => '/addon/home_service/admin/icon-2.png'
            ],
            [
                'name' => '客户管理',
                'key' => 'member_manage',
                'group' => 'member',
                'menu_key' => 'shop_member_list',
                'sort' => 1,
                'page' => '/app/pages/member/index',
                'icon' => '/addon/home_service/admin/icon-4.png'
            ],
        ];

        $menu_keys = $this->getCurrentUserMenuKeys();
        foreach ($apps as $key => $item) {
            $item_menu_key = $item['menu_key'] ?? '';
            if (!empty($item_menu_key) && !in_array($item_menu_key, $menu_keys)) {
                unset($apps[$key]);
            }
        }

        return array_values(array_filter($apps));
    }

    protected function getCurrentUserMenuKeys(): array
    {
        $menu_service = new MenuService();
        if (AuthService::isSuperAdmin()) {
            $menu_list = $this->getAllSiteMenus($menu_service);
            return array_column($menu_list, 'menu_key');
        }

        $auth_service = new AuthService();
        $user_role_info = $auth_service->getAuthRole($this->site_id);
        if (empty($user_role_info)) {
            return [];
        }

        if (!empty($user_role_info['is_admin'])) {
            $menu_list = $this->getAllSiteMenus($menu_service);
            return array_column($menu_list, 'menu_key');
        }

        $role_service = new RoleService();
        $menu_keys = $role_service->getMenuIdsByRoleIds($this->site_id, $user_role_info['role_ids']);
        $menu_list = $this->getSiteMenusByKeys($menu_service, $menu_keys);

        return array_column($menu_list, 'menu_key');
    }

    protected function getAllSiteMenus(MenuService $menu_service): array
    {
        if (method_exists($menu_service, 'getSiteAllMenuList')) {
            return $menu_service->getSiteAllMenuList(AppTypeDict::SITE, 1, 0, 1, 1);
        }

        return $menu_service->getAllMenuList(AppTypeDict::SITE, 1, 0, 1);
    }

    protected function getSiteMenusByKeys(MenuService $menu_service, array $menu_keys): array
    {
        if (empty($menu_keys)) {
            return [];
        }

        if (method_exists($menu_service, 'getSiteMenuListByMenuKeys')) {
            return $menu_service->getSiteMenuListByMenuKeys($this->site_id, $menu_keys, AppTypeDict::SITE, 0, 'all', 1, 1);
        }

        return $menu_service->getMenuListByMenuKeys($this->site_id, $menu_keys, AppTypeDict::SITE, 0, 'all', 1);
    }
}
