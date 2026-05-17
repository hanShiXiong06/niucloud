<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\express;

use addon\hsx_recycle\app\service\admin\express\ExpressProviderConfigService;
use core\base\BaseAdminController;

/**
 * 快递服务商配置管理控制器（管理端）
 * Class ExpressProviderConfig
 * @package addon\hsx_recycle\app\adminapi\controller\express
 */
class ExpressProviderConfig extends BaseAdminController
{
    /**
     * 获取服务商配置列表
     * @return \think\Response
     */
    public function lists()
    {
        $service = new ExpressProviderConfigService();
        $list = $service->getList();
        return success($list);
    }

    /**
     * 获取服务商配置详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        $service = new ExpressProviderConfigService();
        $info = $service->getInfo($id);
        return success($info);
    }

    /**
     * 编辑服务商配置
     * @param int $id
     * @return \think\Response
     */
    public function edit(int $id)
    {
        $data = $this->request->params([
            ['status', ''],
            ['config', ''],
            ['sort', ''],
            ['provider_name', ''],
        ]);

        $service = new ExpressProviderConfigService();
        $service->edit($id, $data);

        return success([], '编辑成功');
    }

    /**
     * 设置默认服务商
     * @param int $id
     * @return \think\Response
     */
    public function setDefault(int $id)
    {
        $service = new ExpressProviderConfigService();
        $service->setDefault($id);

        return success([], '设置成功');
    }

    /**
     * 切换服务商启用状态
     * @param int $id
     * @return \think\Response
     */
    public function toggleStatus(int $id)
    {
        $service = new ExpressProviderConfigService();
        $service->toggleStatus($id);

        return success([], '操作成功');
    }

    /**
     * 获取当前启用的服务商信息
     * @return \think\Response
     */
    public function getActiveProvider()
    {
        $service = new ExpressProviderConfigService();
        $info = $service->getActiveProvider();
        return success($info);
    }

    /**
     * 检查快递服务状态
     * @return \think\Response
     */
    public function checkStatus()
    {
        $service = new ExpressProviderConfigService();
        $status = $service->checkExpressStatus();
        return success($status);
    }
}
