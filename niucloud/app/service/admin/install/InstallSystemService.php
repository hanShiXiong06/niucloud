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

namespace app\service\admin\install;

use app\dict\sys\MenuDict;
use app\model\sys\SysMenu;
use app\service\admin\sys\MenuService;
use app\service\core\menu\CoreMenuService;
use core\base\BaseAdminService;
use think\facade\Cache;
use think\facade\Db;

/**
 * 系统安装
 * Class InstallSystemService
 * @package app\service\admin\init
 */
class InstallSystemService extends BaseAdminService
{

    public $menu_list = [];//菜单列表

    /**
     * 安装
     */
    public function install()
    {
        $this->installMenu();
        return true;
    }

    /**
     * 菜单安装
     */
    public function installMenu()
    {
        $sys_menu = new SysMenu();

        //系统菜单
        $menus = $this->loadMenu();
        Db::name('sys_menu')->where([ [ 'id', '>', 0 ], ['source', '=', MenuDict::SYSTEM] ])->delete();
        //$sys_menu->where([ [ 'id', '>', 0 ] ])->force(true)->delete();
        $sys_menu->replace()->insertAll($menus);
        //插件菜单
        (new CoreMenuService())->refreshAllAddonMenu();
        // 清除缓存
        Cache::tag(MenuService::$cache_tag_name)->clear();
        return true;
    }

    /**
     * 加载菜单
     * @return array
     */
    public function loadMenu()
    {
        //加载系统
        $system_tree = include root_path() . str_replace('/', DIRECTORY_SEPARATOR, "app/dict/menu/admin.php");
        $this->menuTreeToList($system_tree, '');
        $menu_list = $this->menu_list;
        $this->menu_list = [];
        return $menu_list;
    }

    /**
     * 菜单数转为列表
     * @param array $tree
     * @param string $parent_key
     */
    private function menuTreeToList(array $tree, string $parent_key = '')
    {
        if (is_array($tree)) {
            foreach ($tree as $key => $value) {
                $item = [
                    'menu_name' => $value[ 'menu_name' ],
                    'menu_key' => $value[ 'menu_key' ],
                    'parent_key' => $value[ 'parent_key' ] ?? $parent_key,
                    'menu_type' => $value[ 'menu_type' ],
                    'icon' => $value[ 'icon' ] ?? '',
                    'api_url' => $value[ 'api_url' ] ?? '',
                    'router_path' => $value[ 'router_path' ] ?? '',
                    'view_path' => $value[ 'view_path' ] ?? '',
                    'methods' => $value[ 'methods' ] ?? '',
                    'sort' => $value[ 'sort' ] ?? '',
                    'status' => 1,
                    'is_show' => $value[ 'is_show' ] ?? 1,
                    'source' => MenuDict::SYSTEM,
                    'menu_attr' => $value['menu_attr'] ?? ''
                ];
                $refer = $value;
                if (isset($refer[ 'children' ])) {
                    unset($refer[ 'children' ]);
                    $this->menu_list[] = $item;
                    $p_key = $refer[ 'menu_key' ];
                    $this->menuTreeToList($value[ 'children' ], $p_key);
                } else {
                    $this->menu_list[] = $item;
                }
            }
        }
    }

}
