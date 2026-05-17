<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\dashboard;

use addon\hsx_recycle\app\model\dashboard\RecycleDashboardWidget;
use addon\hsx_recycle\app\service\admin\stats\RecycleStatsService;
use app\service\admin\auth\AuthService;
use core\base\BaseAdminService;
use core\exception\AdminException;

/**
 * 回收首页组件配置服务
 * Class RecycleDashboardWidgetService
 * @package addon\hsx_recycle\app\service\admin\dashboard
 */
class RecycleDashboardWidgetService extends BaseAdminService
{
    protected $model;
    protected RecycleStatsService $statsService;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleDashboardWidget();
        $this->statsService = new RecycleStatsService();
    }

    /**
     * 首页内置组件注册表
     * 后续新增指标时，在这里增加 data_key，并在 buildMetricMap 中接入查询即可。
     * @return array
     */
    public static function builtinWidgets(): array
    {
        return [
            'my_signed_devices' => [
                'widget_key' => 'my_signed_devices',
                'widget_name' => '我的签收设备',
                'widget_type' => 'stat',
                'data_key' => 'signed_device_count',
                'data_scope' => 'own',
                'sort' => 10,
                'config' => ['unit' => '台', 'color' => 'blue'],
            ],
            'my_check_count' => [
                'widget_key' => 'my_check_count',
                'widget_name' => '我的质检数量',
                'widget_type' => 'stat',
                'data_key' => 'check_count',
                'data_scope' => 'own',
                'sort' => 20,
                'config' => ['unit' => '台', 'color' => 'green'],
            ],
            'my_price_count' => [
                'widget_key' => 'my_price_count',
                'widget_name' => '我的定价数量',
                'widget_type' => 'stat',
                'data_key' => 'price_count',
                'data_scope' => 'own',
                'sort' => 30,
                'config' => ['unit' => '台', 'color' => 'amber'],
            ],
            'my_payment_count' => [
                'widget_key' => 'my_payment_count',
                'widget_name' => '我的打款数量',
                'widget_type' => 'stat',
                'data_key' => 'payment_count',
                'data_scope' => 'own',
                'sort' => 40,
                'config' => ['unit' => '台', 'color' => 'red'],
            ],
            'site_today_order_count' => [
                'widget_key' => 'site_today_order_count',
                'widget_name' => '今日订单数',
                'widget_type' => 'stat',
                'data_key' => 'today_order_count',
                'data_scope' => 'site',
                'sort' => 100,
                'config' => ['unit' => '单', 'color' => 'blue'],
            ],
            'site_today_check_count' => [
                'widget_key' => 'site_today_check_count',
                'widget_name' => '今日质检数',
                'widget_type' => 'stat',
                'data_key' => 'today_check_count',
                'data_scope' => 'site',
                'sort' => 110,
                'config' => ['unit' => '台', 'color' => 'green'],
            ],
            'site_today_price_count' => [
                'widget_key' => 'site_today_price_count',
                'widget_name' => '今日定价数',
                'widget_type' => 'stat',
                'data_key' => 'today_price_count',
                'data_scope' => 'site',
                'sort' => 120,
                'config' => ['unit' => '台', 'color' => 'amber'],
            ],
            'site_today_payment_amount' => [
                'widget_key' => 'site_today_payment_amount',
                'widget_name' => '今日打款金额',
                'widget_type' => 'stat',
                'data_key' => 'today_payment_amount',
                'data_scope' => 'site',
                'sort' => 130,
                'config' => ['unit' => '元', 'color' => 'red', 'precision' => 2],
            ],
            'site_today_return_count' => [
                'widget_key' => 'site_today_return_count',
                'widget_name' => '今日退货数',
                'widget_type' => 'stat',
                'data_key' => 'today_return_count',
                'data_scope' => 'site',
                'sort' => 140,
                'config' => ['unit' => '台', 'color' => 'gray'],
            ],
            'today_check_breakdown' => [
                'widget_key' => 'today_check_breakdown',
                'widget_name' => '今日质检分类',
                'widget_type' => 'chart',
                'data_key' => 'today_check_breakdown',
                'data_scope' => 'site',
                'sort' => 200,
                'config' => ['chart_type' => 'bar'],
            ],
            'staff_work_chart' => [
                'widget_key' => 'staff_work_chart',
                'widget_name' => '员工工作图表',
                'widget_type' => 'chart',
                'data_key' => 'staff_work_chart',
                'data_scope' => 'site',
                'sort' => 220,
                'config' => ['chart_type' => 'bar'],
            ],
            'staff_work_table' => [
                'widget_key' => 'staff_work_table',
                'widget_name' => '员工工作明细',
                'widget_type' => 'table',
                'data_key' => 'staff_work_table',
                'data_scope' => 'site',
                'sort' => 230,
                'config' => ['layout' => 'table'],
            ],
            'member_stats_overview' => [
                'widget_key' => 'member_stats_overview',
                'widget_name' => '会员概览',
                'widget_type' => 'section',
                'data_key' => 'member_stats_overview',
                'data_scope' => 'site',
                'sort' => 300,
                'config' => ['layout' => 'overview'],
            ],
            'quick_express_ship' => [
                'widget_key' => 'quick_express_ship',
                'widget_name' => '快速寄件',
                'widget_type' => 'action',
                'data_key' => 'quick_express_ship',
                'data_scope' => 'none',
                'sort' => 900,
                'config' => ['action' => 'express_ship'],
            ],
            'quick_express_track' => [
                'widget_key' => 'quick_express_track',
                'widget_name' => '快速查件',
                'widget_type' => 'action',
                'data_key' => 'quick_express_track',
                'data_scope' => 'none',
                'sort' => 910,
                'config' => ['action' => 'express_track'],
            ],
        ];
    }

    /**
     * 初始化内置组件
     * @return void
     */
    public function ensureBuiltinWidgets(): void
    {
        $existsKeys = $this->model
            ->where([['site_id', '=', $this->site_id]])
            ->column('widget_key');

        foreach (self::builtinWidgets() as $widget) {
            if (in_array($widget['widget_key'], $existsKeys, true)) {
                continue;
            }

            $this->model->create([
                'site_id' => $this->site_id,
                'widget_key' => $widget['widget_key'],
                'widget_name' => $widget['widget_name'],
                'widget_type' => $widget['widget_type'],
                'data_key' => $widget['data_key'],
                'data_scope' => $widget['data_scope'],
                'role_ids' => [],
                'uids' => [],
                'config' => $widget['config'] ?? [],
                'status' => 1,
                'sort' => (int)($widget['sort'] ?? 0),
            ]);
        }
    }

    /**
     * 管理端配置列表
     * @return array
     */
    public function getList(): array
    {
        $this->ensureBuiltinWidgets();

        $rows = $this->model
            ->where([['site_id', '=', $this->site_id]])
            ->order('sort asc, widget_id asc')
            ->select()
            ->toArray();

        foreach ($rows as &$row) {
            $row = $this->normalizeRow($row);
            $row['builtin'] = isset(self::builtinWidgets()[$row['widget_key']]) ? 1 : 0;
        }
        unset($row);

        return $rows;
    }

    /**
     * 批量保存组件配置
     * @param array $widgets
     * @return bool
     */
    public function saveWidgets(array $widgets): bool
    {
        $this->ensureBuiltinWidgets();

        foreach ($widgets as $widget) {
            if (!is_array($widget)) {
                continue;
            }

            $widgetId = (int)($widget['widget_id'] ?? 0);
            if ($widgetId <= 0) {
                continue;
            }

            $row = $this->model
                ->where([
                    ['site_id', '=', $this->site_id],
                    ['widget_id', '=', $widgetId],
                ])
                ->findOrEmpty();

            if ($row->isEmpty()) {
                continue;
            }

            $config = $widget['config'] ?? $row['config'] ?? [];
            if (!is_array($config)) {
                $config = [];
            }

            $row->save([
                'widget_name' => $this->normalizeName((string)($widget['widget_name'] ?? $row['widget_name'] ?? '')),
                'data_scope' => $this->normalizeDataScope((string)($widget['data_scope'] ?? $row['data_scope'] ?? 'own')),
                'role_ids' => $this->normalizeIds($widget['role_ids'] ?? []),
                'uids' => $this->normalizeIds($widget['uids'] ?? []),
                'config' => $config,
                'status' => empty($widget['status']) ? 0 : 1,
                'sort' => (int)($widget['sort'] ?? 0),
            ]);
        }

        return true;
    }

    /**
     * 当前用户可见首页配置
     * @param array $params
     * @return array
     */
    public function getVisibleDashboard(array $params = []): array
    {
        $this->ensureBuiltinWidgets();

        $auth = $this->getCurrentAuth();
        $rows = $this->model
            ->where([
                ['site_id', '=', $this->site_id],
                ['status', '=', 1],
            ])
            ->order('sort asc, widget_id asc')
            ->select()
            ->toArray();

        $visibleRows = [];
        foreach ($rows as $row) {
            $row = $this->normalizeRow($row);
            if ($this->canView($row, $auth)) {
                $visibleRows[] = $row;
            }
        }

        $metricMap = $this->buildMetricMap($visibleRows, $params);
        $permissions = [];
        foreach ($visibleRows as &$row) {
            $row['value'] = $metricMap[$row['data_key']] ?? null;
            if ($row['widget_type'] === 'action') {
                $permissions[$row['widget_key']] = true;
            }
        }
        unset($row);

        return [
            'widgets' => $visibleRows,
            'permissions' => $permissions,
            'auth' => $auth,
        ];
    }

    /**
     * 当前登录用户权限上下文
     * @return array
     */
    protected function getCurrentAuth(): array
    {
        $roleInfo = [];
        try {
            $roleInfo = (new AuthService())->getAuthRole($this->site_id) ?: [];
        } catch (\Throwable $e) {
            $roleInfo = [];
        }

        $roleIds = $this->normalizeIds($roleInfo['role_ids'] ?? []);
        $isAdmin = (int)($roleInfo['is_admin'] ?? 0);
        try {
            if (AuthService::isSuperAdmin()) {
                $isAdmin = 1;
            }
        } catch (\Throwable $e) {
            // 非标准请求上下文中无法取默认站点时，按站点角色继续判断。
        }

        return [
            'uid' => (int)$this->uid,
            'site_id' => (int)$this->site_id,
            'is_admin' => $isAdmin,
            'role_ids' => $roleIds,
        ];
    }

    /**
     * 判断组件是否可见
     * @param array $widget
     * @param array $auth
     * @return bool
     */
    protected function canView(array $widget, array $auth): bool
    {
        if (!empty($auth['is_admin'])) {
            return true;
        }

        $roleIds = $widget['role_ids'] ?? [];
        $uids = $widget['uids'] ?? [];

        if (empty($roleIds) && empty($uids)) {
            return true;
        }

        if (in_array((int)$auth['uid'], $uids, true)) {
            return true;
        }

        foreach ($auth['role_ids'] as $roleId) {
            if (in_array((int)$roleId, $roleIds, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 根据可见组件一次性构建指标值
     * @param array $widgets
     * @param array $params
     * @return array
     */
    protected function buildMetricMap(array $widgets, array $params): array
    {
        $dataKeys = array_values(array_unique(array_column($widgets, 'data_key')));
        if (empty($dataKeys)) {
            return [];
        }

        $metricMap = [];

        $needOverview = (bool)array_intersect($dataKeys, [
            'today_order_count',
            'today_check_count',
            'today_price_count',
            'today_payment_amount',
            'today_return_count',
            'today_check_breakdown',
        ]);

        if ($needOverview) {
            $overview = $this->statsService->getOverviewStats($params);
            foreach ($overview as $key => $value) {
                $metricMap[$key] = $value;
            }
        }

        $needOwn = (bool)array_intersect($dataKeys, [
            'signed_device_count',
            'check_count',
            'price_count',
            'payment_amount',
            'return_count',
        ]);

        if ($needOwn) {
            $userStats = $this->statsService->getUserDetailStats([
                'user_id' => $this->uid,
                'start_time' => $params['start_time'] ?? date('Y-m-d'),
                'end_time' => $params['end_time'] ?? date('Y-m-d'),
            ]);
            $currentStats = $userStats[0] ?? [];
            foreach ($currentStats as $key => $value) {
                $metricMap[$key] = $value;
            }
        }

        if (in_array('member_stats_overview', $dataKeys, true)) {
            $metricMap['member_stats_overview'] = $this->statsService->getMemberStatsOverview($params);
        }

        $metricMap['quick_express_ship'] = true;
        $metricMap['quick_express_track'] = true;
        $metricMap['staff_work_chart'] = true;
        $metricMap['staff_work_table'] = true;

        return $metricMap;
    }

    /**
     * 标准化记录
     * @param array $row
     * @return array
     */
    protected function normalizeRow(array $row): array
    {
        $row['widget_id'] = (int)($row['widget_id'] ?? 0);
        $row['site_id'] = (int)($row['site_id'] ?? 0);
        $row['status'] = (int)($row['status'] ?? 0);
        $row['sort'] = (int)($row['sort'] ?? 0);
        $row['role_ids'] = $this->normalizeIds($row['role_ids'] ?? []);
        $row['uids'] = $this->normalizeIds($row['uids'] ?? []);
        $row['config'] = is_array($row['config'] ?? null) ? $row['config'] : [];
        $row['data_scope'] = $this->normalizeDataScope((string)($row['data_scope'] ?? 'own'));
        return $row;
    }

    /**
     * 标准化 ID 数组
     * @param mixed $value
     * @return array
     */
    protected function normalizeIds($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $item) {
            $id = (int)$item;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * 标准化数据范围
     * @param string $scope
     * @return string
     */
    protected function normalizeDataScope(string $scope): string
    {
        $allowed = ['own', 'site', 'assigned', 'none'];
        return in_array($scope, $allowed, true) ? $scope : 'own';
    }

    /**
     * 标准化组件名称
     * @param string $name
     * @return string
     */
    protected function normalizeName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new AdminException('组件名称不能为空');
        }
        return mb_substr($name, 0, 100);
    }
}
