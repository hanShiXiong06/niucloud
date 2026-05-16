<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller\dashboard;

use addon\recycle\app\service\admin\dashboard\RecycleDashboardWidgetService;
use core\base\BaseAdminController;
use think\App;
use think\Response;

/**
 * 回收首页配置控制器
 * Class DashboardConfig
 * @package addon\recycle\app\adminapi\controller\dashboard
 */
class DashboardConfig extends BaseAdminController
{
    protected RecycleDashboardWidgetService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new RecycleDashboardWidgetService();
    }

    /**
     * 首页组件配置列表
     * @return Response
     */
    public function widgets(): Response
    {
        return success($this->service->getList());
    }

    /**
     * 保存首页组件配置
     * @return Response
     */
    public function save(): Response
    {
        $data = $this->request->params([
            ['widgets', []],
        ]);

        $this->service->saveWidgets(is_array($data['widgets']) ? $data['widgets'] : []);
        return success('保存成功');
    }

    /**
     * 当前用户可见首页组件
     * @return Response
     */
    public function visible(): Response
    {
        $params = $this->request->params([
            ['start_time', date('Y-m-d')],
            ['end_time', date('Y-m-d')],
        ]);

        return success($this->service->getVisibleDashboard($params));
    }
}
