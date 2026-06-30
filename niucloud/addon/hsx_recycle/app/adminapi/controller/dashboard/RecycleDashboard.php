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

        return success($this->cached('overview', $params, fn() => $this->metricService->getOverview($params)));
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
     * 看板数据缓存层：避免每次打开都实时扫大表。
     * TTL 按时间段定：含今天的区间数据还在变 → 短缓存(60秒，兼顾新增数据及时可见与挡住高频扫表)；
     * 纯历史区间不变 → 长缓存(1天)。
     */
    protected function cached(string $type, array $params, \Closure $builder): array
    {
        $siteId = $this->request->siteId();
        $endDate = (string)($params['end_time'] ?? date('Y-m-d'));
        $includesToday = substr($endDate, 0, 10) >= date('Y-m-d');
        $ttl = $includesToday ? 60 : 86400;
        $key = 'recycle_dash_' . $type . '_' . $siteId . '_' . md5(json_encode($params));
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
