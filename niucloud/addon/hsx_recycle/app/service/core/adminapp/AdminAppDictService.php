<?php

namespace addon\hsx_recycle\app\service\core\adminapp;

use app\service\core\addon\CoreAddonBaseService;
use app\service\core\site\CoreSiteService;
use think\facade\Cache;
use think\facade\Db;

/**
 * 手机管理端字典加载器。
 *
 * 放在插件内，避免为回收移动管理端向 core/dict 增加专用加载器。
 */
class AdminAppDictService
{
    public function load(array $data = [])
    {
        return $this->loadAdminAppFiles('app_group.php');
    }

    public function loadApps(array $data = [])
    {
        return $this->loadAdminAppFiles('app.php');
    }

    public function loadStat(array $data = [])
    {
        return $this->loadAdminAppFiles('stat.json');
    }

    public function loadTodo(array $data = [])
    {
        return $this->loadAdminAppFiles('todo.json');
    }

    protected function loadAdminAppFiles(string $file)
    {
        $addons = $this->getLocalAddons();
        $temp_files = [];
        $system_temp_file = $this->getDictPath() . 'adminapp' . DIRECTORY_SEPARATOR . $file;

        if (is_file($system_temp_file)) {
            $temp_files[] = $system_temp_file;
        }

        foreach ($addons as $addon) {
            $temp_file = $this->getAddonDictPath($addon) . 'adminapp' . DIRECTORY_SEPARATOR . $file;
            if (is_file($temp_file)) {
                $temp_files[] = $temp_file;
            }
        }

        $temp_datas = $this->loadFiles($temp_files);
        $temp_array = [];

        foreach ($temp_datas as $temp_data) {
            $temp_array = empty($temp_array) ? $temp_data : array_merge($temp_array, $temp_data);
        }

        return $temp_array;
    }

    protected function getLocalAddons()
    {
        if (!file_exists(root_path() . 'install.lock')) {
            return [];
        }

        $headers = request()->header();
        $admin_site_id_name = system_name('admin_site_id_name');
        $api_site_id_name = system_name('api_site_id_name');
        $site_id = $headers[$admin_site_id_name] ?? $headers[$api_site_id_name] ?? 0;

        if ((int) $site_id) {
            $addons = Cache::get("local_install_addons_{$site_id}");
            if (!is_null($addons)) {
                return $addons;
            }

            $prefix = config('database.connections.mysql.prefix');
            $site = Db::name('site')
                ->alias('s')
                ->join(["{$prefix}site_group" => 'sg'], 's.group_id = sg.group_id')
                ->where([['s.site_id', '=', $site_id]])
                ->field('s.app,s.addons,sg.app as site_group_app,sg.addon as site_group_addon')
                ->find();

            $addons = array_unique(array_merge(
                (empty($site['app']) ? [] : json_decode($site['app'], true)),
                (empty($site['addons']) ? [] : json_decode($site['addons'], true)),
                (empty($site['site_group_app']) ? [] : json_decode($site['site_group_app'], true)),
                (empty($site['site_group_addon']) ? [] : json_decode($site['site_group_addon'], true))
            ));

            Cache::tag(CoreSiteService::$cache_tag_name . $site_id)->set("local_install_addons_{$site_id}", $addons);
        } else {
            $addons = Cache::get('local_install_addons');
            if (!is_null($addons)) {
                return $addons;
            }

            $addons = Db::name('addon')->column('key');
            Cache::tag(CoreAddonBaseService::$cache_tag_name)->set('local_install_addons', $addons);
        }

        return $addons;
    }

    protected function getDictPath()
    {
        return root_path() . 'app' . DIRECTORY_SEPARATOR . 'dict' . DIRECTORY_SEPARATOR;
    }

    protected function getAddonDictPath(string $addon)
    {
        return root_path() . 'addon' . DIRECTORY_SEPARATOR . $addon . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'dict' . DIRECTORY_SEPARATOR;
    }

    protected function loadFiles(array $files)
    {
        $default_sort = 100000;
        $files_data = [];

        foreach ($files as $file) {
            $config = include $file;
            if (empty($config)) {
                continue;
            }

            if (isset($config['file_sort'])) {
                $sort = $config['file_sort'];
                unset($config['file_sort']);
                $sort = $sort * 10;
                while (array_key_exists($sort, $files_data)) {
                    $sort++;
                }
                $files_data[$sort] = $config;
            } else {
                $files_data[$default_sort] = $config;
                $default_sort++;
            }
        }

        ksort($files_data);
        return $files_data;
    }
}
