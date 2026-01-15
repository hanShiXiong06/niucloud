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
namespace core\dict;


class AdminApps extends BaseDict
{
    /**
     * 加载手机管理端应用分组
     * @param array $data
     * @return bool|mixed
     */
    public function load(array $data = [])
    {
        $addons = $this->getLocalAddons();
        $temp_files = [];
        $system_temp_file = $this->getDictPath() . "adminapp" . DIRECTORY_SEPARATOR . "app_group.php";


        if (is_file($system_temp_file)) {
            $temp_files[] = $system_temp_file;
        }
        foreach ($addons as $v) {
            $temp_file = $this->getAddonDictPath($v) . "adminapp" . DIRECTORY_SEPARATOR . "app_group.php";
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


    /**
     * 加载手机管理端应用
     * @param array $data
     * @return bool|mixed
     */
    public function loadApps(array $data = [])
    {
        $addons = $this->getLocalAddons();
        $temp_files = [];
        $system_temp_file = $this->getDictPath() . "adminapp" . DIRECTORY_SEPARATOR . "app.php";


        if (is_file($system_temp_file)) {
            $temp_files[] = $system_temp_file;
        }
        foreach ($addons as $v) {
            $temp_file = $this->getAddonDictPath($v) . "adminapp" . DIRECTORY_SEPARATOR . "app.php";
            if (is_file($temp_file)) {
                $temp_files[] = $temp_file;
            }
        }

        $temp_datas = $this->loadFiles($temp_files);

        $temp_array = [];
        foreach ($temp_datas as $temp_data) {
            $temp_array = empty($temp_array) ? $temp_data : array_merge($temp_array, $temp_data);
//            dump($temp_data);
        }
        return $temp_array;
    }

    public function loadStat(array $data = [])
    {
        $addons = $this->getLocalAddons();
        $temp_files = [];
        $system_temp_file = $this->getDictPath() . "adminapp" . DIRECTORY_SEPARATOR . "stat.json";


        if (is_file($system_temp_file)) {
            $temp_files[] = $system_temp_file;
        }
        foreach ($addons as $v) {
            $temp_file = $this->getAddonDictPath($v) . "adminapp" . DIRECTORY_SEPARATOR . "stat.json";
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


    public function loadTodo(array $data = [])
    {
        $addons = $this->getLocalAddons();
        $temp_files = [];
        $system_temp_file = $this->getDictPath() . "adminapp" . DIRECTORY_SEPARATOR . "todo.json";


        if (is_file($system_temp_file)) {
            $temp_files[] = $system_temp_file;
        }
        foreach ($addons as $v) {
            $temp_file = $this->getAddonDictPath($v) . "adminapp" . DIRECTORY_SEPARATOR . "todo.json";
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
}