<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\dashboard;

use addon\hsx_recycle\app\service\admin\dashboard\RecycleDashboardFilterService;
use addon\hsx_recycle\app\service\admin\dashboard\RecycleDashboardMetricService;
use addon\hsx_recycle\app\dict\order\DeviceProgressDict;
use core\base\BaseAdminController;
use think\App;
use think\Response;

/**
 * 回收经营看板控制器
 */
class RecycleDashboard extends BaseAdminController
{
    protected RecycleDashboardMetricService $metricService;
    protected RecycleDashboardFilterService $filterService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->metricService = new RecycleDashboardMetricService();
        $this->filterService = new RecycleDashboardFilterService();
    }

    public function overview(): Response
    {
        $params = $this->request->params([
            ['start_time', date('Y-m-d')],
            ['end_time', date('Y-m-d')],
            ['check_timeout_hours', RecycleDashboardFilterService::DEFAULT_CHECK_TIMEOUT_HOURS],
            ['quote_timeout_hours', RecycleDashboardFilterService::DEFAULT_QUOTE_TIMEOUT_HOURS],
            ['pay_timeout_hours', RecycleDashboardFilterService::DEFAULT_PAY_TIMEOUT_HOURS],
            ['high_cost_amount', RecycleDashboardFilterService::DEFAULT_HIGH_COST_AMOUNT],
        ]);

        // overview 同时含当前待办快照，不能因用户选择历史日期而把待办缓存一天。
        return success($this->metricService->getOverview($params));
    }

    public function trend(): Response
    {
        $params = $this->request->params([
            ['start_time', date('Y-m-d')],
            ['end_time', date('Y-m-d')],
            ['check_timeout_hours', RecycleDashboardFilterService::DEFAULT_CHECK_TIMEOUT_HOURS],
            ['quote_timeout_hours', RecycleDashboardFilterService::DEFAULT_QUOTE_TIMEOUT_HOURS],
            ['pay_timeout_hours', RecycleDashboardFilterService::DEFAULT_PAY_TIMEOUT_HOURS],
            ['high_cost_amount', RecycleDashboardFilterService::DEFAULT_HIGH_COST_AMOUNT],
        ]);

        return success($this->cached('trend', $params, fn() => $this->metricService->getTrend($params)));
    }

    /**
     * 趋势短缓存：历史日期也可能收到补付/延迟结算记录，不能缓存一天。
     * 当前待办快照不走此缓存。
     */
    protected function cached(string $type, array $params, \Closure $builder): array
    {
        $siteId = $this->request->siteId();
        $ttl = 60;
        $key = 'recycle_dash_v2_' . $type . '_' . $siteId . '_' . md5(json_encode($params));
        return cache_remember($key, $builder, 'recycle_dashboard', ['expire' => $ttl]);
    }

    public function metrics(): Response
    {
        return success($this->metricService->getMetrics());
    }

    public function filters(): Response
    {
        return success($this->filterService->getFilters());
    }

    /**
     * 获取设备进度分组定义
     * @return Response
     */
    public function deviceProgressGroups(): Response
    {
        return success(DeviceProgressDict::getGroups());
    }
}
