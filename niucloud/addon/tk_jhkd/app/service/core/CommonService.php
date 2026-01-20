<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud-admin.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\service\core;

use addon\tk_jhkd\app\dict\config\PlatformDict;
use addon\tk_jhkd\app\dict\delivery\KdniaoBrandDict;
use addon\tk_jhkd\app\dict\delivery\XindaBrandDict;
use addon\tk_jhkd\app\dict\delivery\YidaBrandDict;
use addon\tk_jhkd\app\dict\delivery\YisuBrandDict;
use addon\tk_jhkd\app\dict\delivery\YunyangBrandDict;
use app\model\dict\Dict;
use app\service\core\site\CoreSiteService;
use app\service\core\sys\CoreConfigService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\Exception;
use think\facade\Db;

/**
 * 聚合快递公共服务层
 * Class CommonService
 * @package addon\tk_jhkd\service\core\common
 */
class CommonService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getPartnerCommissionConfig($site_id)
    {
        $info = (new CoreConfigService())->getConfig($site_id, 'TK_PARTNER_COMMISSION_CONFIG');
        if (empty($info)) {
            return ['is_use' => 0];
        }
        return $info['value'];
    }

    /**
     * @Notes:验证站点是否有快递分销插件
     * checkFenxiao
     * 2025/1/12  13:51
     * author:TK
     */
    public function checkFenxiao()
    {
        $addons = (new CoreSiteService())->getAddonKeysBySiteId($this->site_id);
        if (!in_array('kd_fenxiao', $addons)) {
            return ['status' => false, 'msg' => '暂无快递分销插件权限'];
        } else {
            return ['status' => true, 'msg' => '拥有快递分销插件'];
        }
    }

    public function getBrand($platform, $delivery_type)
    {
        if ($platform == 'xinda') {
            return XindaBrandDict::getBrand($delivery_type);
        }
        if ($platform == 'yida') {
            return YidaBrandDict::getBrand($delivery_type);
        }
        if ($platform == 'yunyang') {
            return YunyangBrandDict::getBrand($delivery_type);
        }
        if ($platform == 'kdniao') {
            return KdniaoBrandDict::getBrand($delivery_type);
        }
        if ($platform == 'yisu') {
            return YisuBrandDict::getBrand($delivery_type);
        }
    }

    /**
     * @Notes:获取站点的驱动  type 为驱动类
     * @Interface getSiteDriver
     * @param $site_id
     * @param $type
     * @return array
     * @throws Exception
     * @author: TK
     * @Time: 2024/10/7   下午3:47
     */
    public function getSiteDriver($site_id, $type = '')
    {
        $info = (new CoreConfigService())->getConfig((int)$site_id, PlatformDict::getType());
        if (empty($info)) {
            throw new Exception('请先设置三方平台参数');
        } else {
            $config_type = $info['value'];
        }
        $extend = [];
        foreach ($config_type as $k => $v) {
            if ($k != 'default' && $v['is_use'] == 1) {
                $v['type'] = $k;
                $driverInfo = $this->getDriverByType($k);
                if ($driverInfo) {
                    $extend[] = [
                        'driver' => $driverInfo['driver'],
                        'type' => $k,
                        'name' => $driverInfo['name'],
                        'desc' => $driverInfo['desc'],
                        'component' => $driverInfo['component'],
                        'params' => $v
                    ];
                    if ($type != '' && $type == $k) {
                        $driverInfo['params'] = $v;
                        return $driverInfo;
                    }
                }
            }
        }
        if (empty($extend)) {
            throw new Exception('暂未启用平台');
        }
        return $extend;
    }

    public function getSiteAllDriver($site_id, $type = '')
    {
        $info = (new CoreConfigService())->getConfig((int)$site_id, PlatformDict::getType());
        if (empty($info)) {
            throw new Exception('请先设置三方平台参数');
        } else {
            $config_type = $info['value'];
        }
        $extend = [];
        foreach ($config_type as $k => $v) {
            if ($k != 'default') {
                $v['type'] = $k;
                $driverInfo = $this->getDriverByType($k);
                if ($driverInfo) {
                    $extend[] = [
                        'driver' => $driverInfo['driver'],
                        'type' => $k,
                        'name' => $driverInfo['name'],
                        'desc' => $driverInfo['desc'],
                        'component' => $driverInfo['component'],
                        'params' => $v
                    ];
                    if ($type != '' && $type == $k) {
                        $driverInfo['params'] = $v;
                        return $driverInfo;
                    }
                }
            }
        }
        if (empty($extend)) {
            throw new Exception('暂未启用平台');
        }
        return $extend;
    }

    /**
     * @Notes:根据类型获取驱动信息
     * @Interface getDriverByType
     * @param $type
     * @return mixed|null
     * @author: TK
     * @Time: 2024/10/7   下午2:56
     */
    public function getDriverByType($type)
    {
        $platform_type_list = event('JhkdPlatformType')[0];
        $flattened_list = array_merge(...$platform_type_list);
        foreach ($flattened_list as $k => $item) {
            if ($item['type'] == $type) {
                $item['driver'] = $k;
                return $item;
            }
        }
        return null;
    }

    public function getConfig($site_id)
    {
        $info = (new CoreConfigService())->getConfig($site_id, 'TK_JHKD_CONFIG');
        if (empty($info)) {
            $info['value']['is_webhook'] = '0';
        }
        return $info['value'];
    }

    /**
     * 移动文件
     * 初始admin打包移动文件，uni-app-cli框架构建
     */
    public function moveFile()
    {
        $basedir = root_path() . 'addon' . DIRECTORY_SEPARATOR . 'tk_jhkd' . DIRECTORY_SEPARATOR . 'movefile' . DIRECTORY_SEPARATOR;
        $from_admin_dir = $basedir . "admin";
        // 放入的文件
        $rootpath = dirname(root_path()) . DIRECTORY_SEPARATOR;
        $to_admin_dir = $rootpath . "admin";
        // 移动admin迁移文件
        if (file_exists($from_admin_dir)) {
            dir_copy($from_admin_dir, $to_admin_dir);
        }
        return true;
    }

    //获取url
    public function getUrl()
    {
        $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $domain = $_SERVER['HTTP_HOST'];
        if ($isSecure) {
            $url = 'https://' . $domain;
        } else {
            $url = 'http://' . $domain;
        }
        return $url;
    }

    public function getDictName($key, $value)
    {
        $field = 'id,name,key,dictionary,memo,create_time,update_time';
        $this->model = new Dict();
        $info = $this->model->field($field)->where([['key', '=', $key]])->findOrEmpty()->toArray();

        if ($info['dictionary'] == null) {
            return '';
        }
        foreach ($info['dictionary'] as $k => $v) {
            if ($v['value'] == $value) {
                return $v['name'];
            }
        }
    }

    /**
     * 读取json文件转化成数组返回
     * @param $json_file_path //json文件目录
     */
    public function jsonFileToArray(string $json_file_path)
    {
        if (file_exists($json_file_path)) {
            $content_json = @file_get_contents($json_file_path);
            return json_decode($content_json, true);
        } else
            return ["msg" => "文件不存在"];
    }

    /**
     * 读取json文件转化成数组返回
     * @param $json_file_path //json文件目录
     */
    public function writeArrayToJsonFile(array $content, string $file_path)
    {
        $content_json = json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $result = @file_put_contents($file_path, $content_json);
        if (!$result) {
            throw new CommonException($file_path . '文件不存在或者权限不足');
        }
        return true;
    }

    public function getMysqlVersion()
    {
        try {
            // 修正字段名拼写错误
            $result = Db::query("SELECT VERSION() AS version");
            $mysqlVersion = $result[0]['version'] ?? '';
            // 提取主版本号（处理可能的后缀如-log）
            $versionParts = explode('-', $mysqlVersion);
            $mainVersion = $versionParts[0] ?? '';
            if (version_compare($mainVersion, '5.7', '>=') && version_compare($mainVersion, '5.8', '<')) {
                return '5.7';
            }

            if (version_compare($mainVersion, '8.0', '>=') && version_compare($mainVersion, '8.1', '<')) {
                return '8.0';
            }

            throw new CommonException("不支持的MySQL版本: {$mysqlVersion}，仅支持5.7和8.0版本");

        } catch (\Exception $e) {
            throw new CommonException("获取MySQL版本失败: " . $e->getMessage());
        }
    }
}