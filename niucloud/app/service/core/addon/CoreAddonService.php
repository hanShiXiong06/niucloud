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

namespace app\service\core\addon;

use app\dict\addon\AddonDict;
use app\model\addon\Addon;
use app\service\core\niucloud\CoreModuleService;
use think\db\exception\DbException;
use think\facade\Cache;
use Throwable;

/**
 * 安装服务层
 * Class CoreInstallService
 * @package app\service\core\install
 */
class CoreAddonService extends CoreAddonBaseService
{
    /**
     * 牛云应用市场数据只用于补充授权、版本和到期信息，不应阻塞本地插件列表。
     * 缓存中同时保留过期数据，远端短暂不可用时仍可返回本地可用结果。
     */
    private const ONLINE_MODULE_CACHE = 'niucloud_online_module_list';
    private const ONLINE_MODULE_FRESH_SECONDS = 600;
    private const ONLINE_MODULE_RETRY_SECONDS = 60;
    private const ONLINE_MODULE_STALE_SECONDS = 86400;

    public function __construct()
    {
        parent::__construct();
        $this->model = new Addon();
    }

    public function getInitList()
    {
        return [
            'type_list' => AddonDict::getType()
        ];
    }

    /**
     * 获取已下载的插件
     * @return array
     */
    public function getLocalAddonList(bool $with_assets = false)
    {
        $list = [];
        $online_app_list = $online_apps = [];
        $install_addon_list = $this->model->append(['status_name'])->column('title, icon, key, desc, status, author, version, install_time, update_time, cover', 'key');
        $error = '';
        $niucloud_module_list = $this->getOnlineModuleList($error);
        if (!empty($niucloud_module_list)) {
            foreach ($niucloud_module_list as $v) {
                $data = array(
                    'app_id' => $v['app']['app_id'],
                    'title' => $v['app']['app_name'],
                    'desc' => $v['app']['app_desc'],
                    'key' => $v['app']['app_key'] ?? '',
                    'version' => $v['version'] ?? '',
                    'author' => $v['site_name'],
                    'author_phone' => $v['site_phone'],
                    'expire_time' => $v['expire_time'],
                    'type' => $v['app']['app_type'],
                    'support_app' => $v['app']['support_channel'] ?? [],
                    'is_download' => false,
                    'is_local' => false,
                    'icon' => $v['app']['app_logo'],
                    'cover' => $v['app']['window_logo'][0],
                );
                $data['install_info'] = $install_addon_list[$v['app']['app_key']] ?? [];
                //给安装的插件中为授权插件或应用的数据赋值过期时间
                if (isset($install_addon_list[$v['app']['app_key']])) {
                    $install_addon_list[$v['app']['app_key']]['expire_time'] = $v['expire_time'];
                }

                $list[$v['app']['app_key']] = $data;
            }
            $online_app_list = array_column($list, 'key');
            $online_apps = array_column($list, 'app_id', 'key');
        }
        $files = get_files_by_dir($this->addon_path);
        if (!empty($files)) {
            foreach ($files as $path) {
                $data = $this->getAddonConfig($path);
                if (isset($data['key'])) {
                    $key = $data['key'];
                    $is_installed = isset($install_addon_list[$key]);
                    $public_icon = public_path() . 'addon' . DIRECTORY_SEPARATOR . $key . DIRECTORY_SEPARATOR . 'icon.png';
                    $public_cover = public_path() . 'addon' . DIRECTORY_SEPARATOR . $key . DIRECTORY_SEPARATOR . 'cover.png';
                    // 已安装插件直接返回静态资源地址；仅应用市场需要为未安装插件携带 Base64 预览图。
                    // 后台侧栏默认请求不再重复读取并传输几十 MB 的图片数据。
                    $data['icon'] = $is_installed && is_file($public_icon)
                        ? '/addon/' . $key . '/icon.png'
                        : ($with_assets && is_file($data['icon']) ? image_to_base64($data['icon']) : '');
                    $data['cover'] = $is_installed && is_file($public_cover)
                        ? '/addon/' . $key . '/cover.png'
                        : ($with_assets && is_file($data['cover']) ? image_to_base64($data['cover']) : '');
                    $data['install_info'] = $install_addon_list[$key] ?? [];
                    $data['is_download'] = true;
                    $data['is_local'] = !in_array($data['key'], $online_app_list);
                    $data['version'] = isset($list[$data['key']]) ? $list[$data['key']]['version'] : $data['version'];
                    $data['app_id'] = in_array($data['key'], $online_app_list) ? $online_apps[$data['key']] : 0;
                    $data['author_phone'] = '';
                    $data['expire_time'] = !isset($install_addon_list[$data['key']]) ? '长期有效' : ($install_addon_list[$data['key']]['expire_time'] ?? '');
                    $list[$key] = $data;
                }
            }
        }
        return ['list' => $list, 'error' => $error];
    }

    /**
     * 获取牛云应用市场模块。十分钟内直接命中缓存；刷新失败时使用一天内的旧数据。
     */
    private function getOnlineModuleList(string &$error = ''): array
    {
        $cached = Cache::get(self::ONLINE_MODULE_CACHE, []);
        if (is_array($cached)
            && isset($cached['data'], $cached['refresh_after'])
            && is_array($cached['data'])
            && (int) $cached['refresh_after'] > time()) {
            return $cached['data'];
        }

        try {
            $list = (new CoreModuleService())->getModuleList()['data'] ?? [];
            if (!is_array($list)) $list = [];
            Cache::set(self::ONLINE_MODULE_CACHE, [
                'data' => $list,
                'refresh_after' => time() + self::ONLINE_MODULE_FRESH_SECONDS,
            ], self::ONLINE_MODULE_STALE_SECONDS);
            return $list;
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $fallback = is_array($cached['data'] ?? null) ? $cached['data'] : [];
            // 远端不可用时短暂负缓存，避免每次打开后台都再次等待超时。
            Cache::set(self::ONLINE_MODULE_CACHE, [
                'data' => $fallback,
                'refresh_after' => time() + self::ONLINE_MODULE_RETRY_SECONDS,
            ], self::ONLINE_MODULE_STALE_SECONDS);
            return $fallback;
        }
    }

    /**
     * 已下载的插件数量
     * @return int
     */
    public function getLocalAddonCount()
    {
        $files = get_files_by_dir($this->addon_path);
        return count($files);
    }

    /**
     * 获取已安装插件数量
     * @param array $where
     * @return int
     * @throws DbException
     */
    public function getCount(array $where = [])
    {

        return $this->model->where($where)->count();
    }

    /**
     * 安装的插件分页
     * @param array $where
     * @return array
     * @throws DbException
     * @throws DbException
     */
    public function getPage(array $where)
    {
        $field = 'id, title, key, desc, version, status, icon, create_time, install_time';
        $search_model = $this->model->withSearch(['title'], $where)->field($field)->order('id desc');
        return $this->pageQuery($search_model);
    }

    /**
     * 插件详情
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        return $this->model->where([['id', '=', $id]])->findOrEmpty()->toArray();
    }

    /**
     * 设置插件(安装或更新)
     * @param array $params
     * @return true
     */
    public function set(array $params)
    {
        $title = $params['title'];
        $key = $params['key'];
        $addon = $this->model->where([
            ['key', '=', $key],
        ])->findOrEmpty();
        $version = $params['version'];//版本号
        $desc = $params['desc'];
        $icon = $params['icon'];
        $data = array(
            'title' => $title,
            'version' => $version,
            'status' => 1,
            'desc' => $desc,
            'icon' => $icon,
            'key' => $key,
            'compile' => $params['compile'] ?? [],
            'type' => $params['type'],
            'support_app' => $params['support_app'] ?? ''
        );
        if ($addon->isEmpty()) {
            $data['install_time'] = time();
            $this->model->create($data);
        } else {
            $data['update_time'] = time();
            $addon->save($data);
        }
        return true;
    }

    /**
     * 通过key查询插件
     * @param string $key
     * @return array
     */
    public function getInfoByKey(string $key)
    {
        return $this->model->where([['key', '=', $key]])->findOrEmpty()->toArray();
    }

    /**
     * 通过插件名删除插件
     * @param string $key
     * @return true
     */
    public function delByKey(string $key)
    {
        $this->model->where([['key', '=', $key]])->delete();
        return true;
    }

    /**
     * 修改插件状态
     * @param int $id
     * @param int $status
     * @return true
     */
    public function setStatus(int $id, int $status)
    {
        $this->model->where([['id', '=', $id]])->update(['status' => $status]);
        return true;
    }

    public function getAppList()
    {
        return event('addon', []);
    }

    /**
     * 查询已安装的有效的应用
     * @return array
     */
    public function getInstallAddonList()
    {
        $addon_list = $this->model->where([['status', '=', AddonDict::ON]])->append(['status_name'])->column('title, icon, key, desc, status, type, support_app', 'key');
        if (!empty($addon_list)) {
            foreach ($addon_list as &$data) {
                // 已安装插件的资源已经发布到 public/addon，直接返回静态地址。
                // 避免每次请求读取大图并转换为 Base64，显著减少 CPU、内存和响应体积。
                $data['icon'] = '/addon/' . $data['key'] . '/icon.png';
            }
            unset($data);
        }
        return $addon_list;
    }

    /**
     * 开发者插件
     * @return array
     */
    public function getAddonDevelopList(string $search = '')
    {
        $list = [];

        $install_addon_list = (new Addon())->append(['status_name', 'type_name'])->column('title, icon, key, desc, status, author, version, install_time, update_time, cover, type', 'key');
        $files = get_files_by_dir($this->addon_path);
        if (!empty($files)) {
            $core_addon_service = new CoreAddonService();
            foreach ($files as $path) {
                $data = $core_addon_service->getAddonConfig($path);
                if (isset($data['key'])) {
                    $key = $data['key'];
                    $data['install_info'] = $install_addon_list[$key] ?? [];
                    $data['icon'] = is_file($data['icon']) ? image_to_base64($data['icon']) : '';
                    $data['cover'] = is_file($data['cover']) ? image_to_base64($data['cover']) : '';
                    $data['is_download'] = true;
                    $data['type_name'] = empty($data['type']) ? '' : AddonDict::getType()[$data['type']] ?? '';
                    $list[$key] = $data;
                }
            }
        }

        if ($search) {
            foreach ($list as $k => $v) {
                if (!str_contains($v['title'], $search)) unset($list[$k]);
            }
        }
        return array_values($list);
    }

    /**
     * 应用详情
     * @param string $key
     * @return array
     */
    public function getAddonDevelopInfo(string $key)
    {
        $dir = $this->addon_path . $key . DIRECTORY_SEPARATOR;
        if (!is_dir($dir)) return [];
        $core_addon_service = new CoreAddonService();

        $data = $core_addon_service->getAddonConfig($key);
        if (isset($data['key'])) {
            $data['icon'] = is_file($data['icon']) ? image_to_base64($data['icon']) : '';
            $data['cover'] = is_file($data['cover']) ? image_to_base64($data['cover']) : '';
            $data['type_name'] = empty($data['type']) ? '' : AddonDict::getType()[$data['type']] ?? '';
        }
        if (isset($data['support_app']) && !empty($data['support_app'])) {
            $data['support_type'] = 2;
        } else {
            $data['support_type'] = 1;
        }
        return $data;
    }

    /**
     * 获取首页应用标签
     * @return array
     */
    public function getIndexAddonLabelList()
    {
        return (new CoreModuleService())->getIndexModuleLabelList()['data'] ?? [];
    }

    /**
     * 获取首页应用
     * @param int $label_id
     * @return array
     */
    public function getIndexAddonList($label_id)
    {
        return (new CoreModuleService())->getIndexModuleList($label_id)['data'] ?? [];
    }

}
