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

namespace addon\wj_books\app\adminapi\controller\wj_books_config;

use addon\wj_books\app\service\admin\wj_books_config\WjBooksConfigService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 旧书回收系统配置控制器
 * Class WjBooksConfigController
 * @package addon\wj_books\app\adminapi\controller\wj_books_config
 */
class WjBooksConfigController extends BaseAdminController
{
    /**
     * 获取旧书回收系统配置
     * @return Response
     */
    public function get()
    {
        $service = new WjBooksConfigService();
        return success($service->getConfig());
    }

    /**
     * 更新旧书回收系统配置
     * @return Response
     */
    public function update()
    {
        $data = $this->request->put();
        $service = new WjBooksConfigService();
        $service->updateConfig($data);
        return success('保存成功');
    }
} 