<?php
// HSX_RECYCLE_ADMINAPP_COMPAT_BRIDGE
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

use addon\hsx_recycle\app\service\core\adminapp\AdminAppDictService;

/**
 * 手机管理端字典兼容加载器。
 *
 * 线上如果仍然存在旧的 app/service/core/adminapp 服务，会通过 DictLoader('AdminApps')
 * 进入这里。真实字典加载逻辑保留在 hsx_recycle 插件内，避免继续扩散核心业务代码。
 */
class AdminApps extends BaseDict
{
    protected function initialize(array $config = [])
    {
    }

    public function load(array $data = [])
    {
        return (new AdminAppDictService())->load($data);
    }

    public function loadApps(array $data = [])
    {
        return (new AdminAppDictService())->loadApps($data);
    }

    public function loadStat(array $data = [])
    {
        return (new AdminAppDictService())->loadStat($data);
    }

    public function loadTodo(array $data = [])
    {
        return (new AdminAppDictService())->loadTodo($data);
    }
}
