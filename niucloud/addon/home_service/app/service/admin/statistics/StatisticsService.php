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

namespace addon\home_service\app\service\admin\statistics;


use addon\home_service\app\dict\cash_out\CashOutDict;
use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\dict\order\RefundDict;
use addon\home_service\app\model\cash_out\CashOut;
use addon\home_service\app\model\goods\GoodsCategory;
use addon\home_service\app\model\order\Evaluate;
use addon\home_service\app\model\order\Order;
use addon\home_service\app\model\order\OrderRefund;
use addon\home_service\app\model\store\Store;
use addon\home_service\app\model\store\StoreApplication;
use addon\home_service\app\model\technician\Technician;
use addon\home_service\app\model\technician\TechnicianApplication;
use app\model\member\Member;
use app\model\site\Site;
use core\base\BaseAdminService;
use addon\home_service\app\service\core\statistics\CoreEvaluateService;
use core\exception\AdminException;
use think\facade\Db;


/**
 * 统计
 * Class StatisticsService
 */
class  StatisticsService extends BaseAdminService
{

    public function __construct()
    {
        parent::__construct();
    }


    /**
     * 首页-基础数据
     */
    public function getBasicData()
    {
        $site_create_time = (new Site())->where([['site_id', '=', $this->site_id]])->value('create_time');

        // 时间范围
        $monthStart      = strtotime(date('Y-m-01 00:00:00'));
        $monthEnd        = strtotime(date('Y-m-01 00:00:00', strtotime('+1 month')));
        $lastMonthStart  = strtotime(date('Y-m-01 00:00:00', strtotime('-1 month')));
        $lastMonthEnd    = $monthStart;

        // ================= 订单统计 =================
        $orderResult = (new Order())
            ->where([['site_id', '=', $this->site_id], ['order_status', '=', OrderDict::FINISH]])
            ->fieldRaw("
            COUNT(*) as total_orders,
            SUM(CASE WHEN create_time >= {$monthStart} AND create_time < {$monthEnd} THEN 1 ELSE 0 END) as month_orders,
            SUM(CASE WHEN create_time >= {$lastMonthStart} AND create_time < {$lastMonthEnd} THEN 1 ELSE 0 END) as last_month_orders
        ")
            ->find();

        $monthOrders      = (int)$orderResult['month_orders'];
        $lastMonthOrders  = (int)$orderResult['last_month_orders'];
        $totalOrders      = (int)$orderResult['total_orders'];
        $orderGrowthRate  = $lastMonthOrders == 0 ? ($monthOrders > 0 ? 100 : 0) : round(($monthOrders - $lastMonthOrders)/$lastMonthOrders*100, 2);

        // ================= 师傅统计 =================
        $techResult = (new Technician())
            ->where([['site_id', '=', $this->site_id]])
            ->fieldRaw("
            COUNT(*) as total_technicians,
            SUM(CASE WHEN create_time >= {$monthStart} AND create_time < {$monthEnd} THEN 1 ELSE 0 END) as month_technicians,
            SUM(CASE WHEN create_time >= {$lastMonthStart} AND create_time < {$lastMonthEnd} THEN 1 ELSE 0 END) as last_month_technicians
        ")
            ->find();

        $monthTechnicians     = (int)$techResult['month_technicians'];
        $lastMonthTechnicians = (int)$techResult['last_month_technicians'];
        $totalTechnicians     = (int)$techResult['total_technicians'];
        $techGrowthRate = $lastMonthTechnicians == 0 ? ($monthTechnicians > 0 ? 100 : 0) : round(($monthTechnicians - $lastMonthTechnicians)/$lastMonthTechnicians*100, 2);

        // ================= 门店统计 =================
        $storeResult = (new Store())
            ->where([['site_id', '=', $this->site_id]])
            ->fieldRaw("
            COUNT(*) as total_stores,
            SUM(CASE WHEN create_time >= {$monthStart} AND create_time < {$monthEnd} THEN 1 ELSE 0 END) as month_stores,
            SUM(CASE WHEN create_time >= {$lastMonthStart} AND create_time < {$lastMonthEnd} THEN 1 ELSE 0 END) as last_month_stores
        ")
            ->find();

        $monthStores     = (int)$storeResult['month_stores'];
        $lastMonthStores = (int)$storeResult['last_month_stores'];
        $totalStores     = (int)$storeResult['total_stores'];
        $storeGrowthRate = $lastMonthStores == 0 ? ($monthStores > 0 ? 100 : 0) : round(($monthStores - $lastMonthStores)/$lastMonthStores*100, 2);

        // ================= 用户统计 =================
        $memberResult = (new Member())
            ->where([['site_id', '=', $this->site_id]])
            ->fieldRaw("
            COUNT(*) as total_members,
            SUM(CASE WHEN create_time >= {$monthStart} AND create_time < {$monthEnd} THEN 1 ELSE 0 END) as month_members,
            SUM(CASE WHEN create_time >= {$lastMonthStart} AND create_time < {$lastMonthEnd} THEN 1 ELSE 0 END) as last_month_members
        ")
            ->find();
        $monthMembers     = (int)$memberResult['month_members'];
        $lastMonthMembers = (int)$memberResult['last_month_members'];
        $totalMembers     = (int)$memberResult['total_members'];
        $memberGrowthRate = $lastMonthMembers == 0 ? ($monthMembers > 0 ? 100 : 0) : round(($monthMembers - $lastMonthMembers)/$lastMonthMembers*100, 2);
        // ================= 返回结果 =================
        return [
            'order_month'      => $monthOrders,
            'order_total'      => $totalOrders,
            'order_growth_rate' => $orderGrowthRate,
            'technician_month'      => $monthTechnicians,
            'technician_total'      => $totalTechnicians,
            'technician_growth_rate' => $techGrowthRate,
            'store_month'      => $monthStores,
            'store_total'      => $totalStores,
            'store_growth_rate' => $storeGrowthRate,
            'member_month'      => $monthMembers,
            'member_total'      => $totalMembers,
            'member_growth_rate' => $memberGrowthRate,
            'site_create_time' => date('Y-m-d',$site_create_time),
        ];
    }

    /**
     * 首页-工单数据
     */
    public function getWorkOrderData()
    {
        $status_list = [
            OrderDict::DISPATCH,
            OrderDict::WAIT_SERVICE,
            OrderDict::IN_SERVICE,
            OrderDict::ABNORMAL_ORDER,
            OrderDict::WAIT_CHECK,
            OrderDict::FINISH,
        ];

        $query = (new Order())
            ->where([['site_id', '=', $this->site_id]])
            ->field('order_status, COUNT(*) as total')
            ->group('order_status')
            ->select()
            ->toArray();

        $counts = array_column($query, 'total', 'order_status');
        $result = [];
        foreach ($status_list as $item) {
            $result[$item] = $counts[$item] ?? 0;
        }
        return $result;
    }

    /**
     * 首页-代办总览
     */
    public function getTodoData()
    {
        // ================= 代办订单 =================
        $order = (new Order())
            ->where([['site_id', '=', $this->site_id]])
            ->fieldRaw("
            SUM(CASE WHEN order_status = 'wait_dispatch' THEN 1 ELSE 0 END) as wait_dispatch,
            SUM(CASE WHEN refund_status = 'wait_refund' THEN 1 ELSE 0 END) as wait_refund
        ")->find();
        // ================= 代办审核 =================
        $technician_application_count = (new TechnicianApplication())->where([['site_id', '=', $this->site_id],['audit_status', '=', 0]])->count();
        $store_application_count = (new StoreApplication())->where([['site_id', '=', $this->site_id],['audit_status', '=', 0]])->count();

        // ================= 资金受理 =================

        $cash_out = (new CashOut())
            ->field('source, SUM(apply_money) as total_apply_money')
            ->where([
                ['site_id', '=', $this->site_id],
                ['status', '=', CashOutDict::WAIT_TRANSFER]
            ])
            ->group('source')
            ->column('SUM(apply_money) as total_apply_money', 'source');

        $technician_cash_out_money = number_format($cash_out[CashOutDict::TECHNICIAN] ?? 0, 2, '.', '');
        $store_cash_out_money = number_format($cash_out[CashOutDict::STORE] ?? 0, 2, '.', '');

        return [
            'wait_dispatch' => (int)$order['wait_dispatch'],
            'wait_refund'   => (int)$order['wait_refund'],
            'technician_application_count'   => (int)$technician_application_count,
            'store_application_count'   => (int)$store_application_count,
            'technician_cash_out_money'   => $technician_cash_out_money,
            'store_cash_out_money'   => $store_cash_out_money,
        ];
    }

    /**
     * 首页-交易趋势
     */
    public function getTradingTrend($params)
    {
        $query = (new Order())->where([
            ['site_id', '=', $this->site_id],
            ['order_status', '=', OrderDict::FINISH]
        ]);

        $type = $params['date_type'] ?? 'month';

        switch($type) {
            case 'week':
                $start_time = strtotime("this week Monday 00:00:00");
                $end_time   = strtotime("this week Sunday 23:59:59");
                $format = '%Y-%m-%d'; // 按天显示
                break;
            case 'month':
                $start_time = strtotime(date('Y-m-01 00:00:00'));
                $end_time   = strtotime(date('Y-m-t 23:59:59'));
                $format = '%Y-%m-%d'; // 按天显示
                break;
            case 'year':
                $start_time = strtotime(date('Y-01-01 00:00:00'));
                $end_time   = strtotime(date('Y-12-31 23:59:59'));
                $format = '%Y-%m'; // 按月显示
                break;
            default:
                $start_time = strtotime(date('Y-m-01 00:00:00'));
                $end_time   = strtotime(date('Y-m-t 23:59:59'));
                $format = '%Y-%m-%d';
        }

        $query->whereBetween('create_time', [$start_time, $end_time]);

        $data = $query->fieldRaw("FROM_UNIXTIME(create_time, '$format') as time_key, COUNT(order_id) as order_count, SUM(order_money) as total_amount")
            ->group('time_key')
            ->order('time_key asc')
            ->select()
            ->toArray();

        // 补全时间段中空数据
        $time_list = $this->generateTimeLine($start_time, $end_time, $type);
        $result = [];
        foreach($time_list as $time) {
            $found = array_filter($data, fn($d) => $d['time_key'] == $time);
            $result[] = [
                'time_key' => $time,
                'order_count' => $found ? (int) array_values($found)[0]['order_count'] : 0,
                'total_amount' => $found ? (float) number_format(array_values($found)[0]['total_amount'], 2, '.', '') : 0.00
            ];
        }

        return $result;
    }

    /**
     * 生成折线图时间轴
     */
    private function generateTimeLine($start_time, $end_time, $type)
    {
        $list = [];
        $current = $start_time;
        switch($type) {
            case 'week':
            case 'month':
            case 'custom':
                while($current <= $end_time){
                    $list[] = date('Y-m-d', $current);
                    $current = strtotime('+1 day', $current);
                }
                break;
            case 'year':
                while($current <= $end_time){
                    $list[] = date('Y-m', $current);
                    $current = strtotime('+1 month', $current);
                }
                break;
        }
        return $list;
    }

    /**
     * 服务类型占比
     */
    public function getCategoryRate()
    {
        $result = (new GoodsCategory())
            ->alias('category')
            ->leftJoin('home_service_order order', "category.category_id = order.category_id AND order.order_status = 'finish' AND order.delete_time = 0")
            ->field('category.category_id, category.category_name, SUM(order.pay_money) as total_money')
            ->where([
                ['category.site_id', '=', $this->site_id],
            ])
            ->group('category.category_id, category.category_name')
            ->order('total_money', 'desc')
            ->limit(6)
            ->select()
            ->toArray();

        foreach ($result as &$value){
            $value['total_money'] = empty($value['total_money']) ? 0 : number_format($value['total_money'], 2, '.', '');
        }

        return $result;
    }

    /**
     * 首页-热门服务排行
     */
    public function getPopularServiceRank($where)
    {
        if (empty($where['stat_type']) || !in_array($where['stat_type'],['order_count','positive_rate'])) throw new AdminException('STATISTICS_TYPE_ERROR');

        $query = (new GoodsCategory())
            ->alias('category')
            ->leftJoin('home_service_order order', "category.category_id = order.category_id AND order.order_status = 'finish' AND order.delete_time = 0");
        if ($where['stat_type'] == 'order_count'){
            $query = $query->field('category.category_id, category.category_name, COUNT(order.order_id) as count');
        }elseif ($where['stat_type'] == 'positive_rate'){
            $query = $query->leftJoin('home_service_goods_evaluate e', "order.order_id = e.order_id AND e.is_audit = 2") // 只统计审核通过的评价
            ->field("
                category.category_id, 
                category.category_name, 
                COUNT(e.evaluate_id) AS evaluate_count,
                SUM(CASE WHEN e.scores >= 4 THEN 1 ELSE 0 END) AS good_count,
                CASE 
                    WHEN COUNT(e.evaluate_id) = 0 THEN 0 
                    ELSE ROUND(SUM(CASE WHEN e.scores >= 4 THEN 1 ELSE 0 END) / COUNT(e.evaluate_id) * 100, 2) 
                END AS count
            ");
        };

        $result = $query->where([
                ['category.site_id', '=', $this->site_id],
            ])
            ->group('category.category_id, category.category_name')
            ->order('count', 'desc')
            ->limit(6)
            ->select()
            ->toArray();

        return $result;
    }

    /**
     * 首页-师傅排行榜
     */
    public function getTechnicianRank($where)
    {
        if (empty($where['stat_type']) || !in_array($where['stat_type'],['order_count','evaluate_avg_scores'])) throw new AdminException('STATISTICS_TYPE_ERROR');

        $result = (new Technician())
            ->field('id, headimg, real_name, evaluate_avg_scores, order_num as order_count, level_id, category_id')
            ->where([
                ['site_id', '=', $this->site_id],
            ])
            ->with(
                [
                    'level' => function ($query) {
                        $query->field('level_id,level_name, order_rate');
                    },
                ]
            )
            ->order($where['stat_type'], 'desc')
            ->append(['category_name'])
            ->limit(10)
            ->select()
            ->toArray();

        return $result;
    }

    /**
     * 师傅报表
     */
    public function getTechnicianStatistics($where)
    {
        $orderField = $where['stat_type'];
        // 时间筛选条件
        $timeRange = null;
        switch ($where['date_type']) {
//            case 'day':
//                $timeRange = [strtotime('today'), strtotime('tomorrow') - 1];
//                break;
            case 'week':
                $timeRange = [strtotime('monday this week'), strtotime('sunday this week 23:59:59')];
                break;
//            case 'season': // 季度
//                $season = ceil(date('n')/3); // 当前季度
//                $seasonStart = strtotime(date('Y-'.(($season-1)*3+1).'-01 00:00:00'));
//                $seasonEnd   = strtotime(date('Y-'.($season*3).'-t 23:59:59'));
//                $timeRange = [$seasonStart, $seasonEnd];
//                break;
            case 'month':
                $timeRange = [strtotime(date('Y-m-01 00:00:00')), strtotime(date('Y-m-t 23:59:59'))];
                break;
            case 'year':
                $timeRange = [strtotime(date('Y-01-01 00:00:00')), strtotime(date('Y-12-31 23:59:59'))];
                break;
            case 'custom':
                if (empty($where['date']) || count($where['date']) !== 2) {
                    throw new AdminException('自定义时间范围不能为空');
                }
                [$startTime, $endTime] = $where['date'];
                $timeRange = [strtotime($startTime.'00:00:00'), strtotime($endTime.'23:59:59')];
                break;
        }
        // 动态拼 CASE WHEN 的时间条件
        $timeCondition = '';
        if ($timeRange) {
            $timeCondition = " AND o.finish_time BETWEEN {$timeRange[0]} AND {$timeRange[1]} ";
        }

        $list  = (new Technician())
            ->alias('t')
            ->leftJoin('home_service_order o', 't.id = o.technician_id AND (o.order_status = "finish" OR o.refund_status = "refund_completed")')
            ->where('t.site_id', $this->site_id)
            ->fieldRaw("
                t.id AS technician_id,
                t.real_name,
                t.headimg,
                COUNT(CASE WHEN o.order_status = 'finish' {$timeCondition} THEN o.order_id END) AS service_order_count,
                IFNULL(SUM(CASE WHEN o.order_status = 'finish' {$timeCondition} THEN o.pay_money ELSE 0 END), 0) AS total_order_money,
                IFNULL(SUM(CASE WHEN o.order_status = 'finish' {$timeCondition} THEN (o.technician_commission + o.technician_additional_commission) ELSE 0 END), 0) AS total_commission,
                SUM(CASE WHEN o.dispatch_timeout_time > 0 {$timeCondition} THEN 1 ELSE 0 END) AS timeout_count
            ")
            ->limit(10)
            ->group('t.id')
            ->order($orderField, 'desc')->select()->toArray();

            $technicianIds = array_column($list,'technician_id');
            $evaluateTimeStatsMap = (new CoreEvaluateService)->batchGetTimeRangeStats($this->site_id, 'technician_id', $technicianIds, 0, $timeRange[0], $timeRange[1]);
            foreach ($list as &$value){
                $value['evaluate_avg_scores'] = 0;
                if (isset($evaluateTimeStatsMap[$value['technician_id']])){
                    $total_evaluate_count = $evaluateTimeStatsMap[$value['technician_id']]['total_evaluate_count'];
                    $total_scores = $evaluateTimeStatsMap[$value['technician_id']]['total_scores'];
                    if($total_evaluate_count >0 && $total_scores > 0){
                        $value['evaluate_avg_scores'] = round($total_scores / $total_evaluate_count, 1);
                    }
                }
            }
            return $list;
    }

    /**
     * 机构报表
     */
    public function getStoreStatistics($where)
    {
        $orderField = $where['stat_type'];
        // 时间筛选条件
        $timeRange = null;
        switch ($where['date_type']) {
//            case 'day':
//                $timeRange = [strtotime('today'), strtotime('tomorrow') - 1];
//                break;
            case 'week':
                $timeRange = [strtotime('monday this week'), strtotime('sunday this week 23:59:59')];
                break;
//            case 'season': // 季度
//                $season = ceil(date('n')/3); // 当前季度
//                $seasonStart = strtotime(date('Y-'.(($season-1)*3+1).'-01 00:00:00'));
//                $seasonEnd   = strtotime(date('Y-'.($season*3).'-t 23:59:59'));
//                $timeRange = [$seasonStart, $seasonEnd];
//                break;
            case 'month':
                $timeRange = [strtotime(date('Y-m-01 00:00:00')), strtotime(date('Y-m-t 23:59:59'))];
                break;
            case 'year':
                $timeRange = [strtotime(date('Y-01-01 00:00:00')), strtotime(date('Y-12-31 23:59:59'))];
                break;
            case 'custom':
                if (empty($where['date']) || count($where['date']) !== 2) {
                    throw new AdminException('自定义时间范围不能为空');
                }
                [$startTime, $endTime] = $where['date'];
                $timeRange = [strtotime($startTime.'00:00:00'), strtotime($endTime.'23:59:59')];
                break;
        }

        // 动态拼 CASE WHEN 的时间条件
        $timeCondition = '';
        if ($timeRange) {
            $timeCondition = " AND o.finish_time BETWEEN {$timeRange[0]} AND {$timeRange[1]} ";
        }

        $list  = (new Store())
            ->alias('s')
            ->leftJoin('home_service_order o', 's.store_id = o.store_id AND (o.order_status = "finish" OR o.refund_status = "refund_completed")')
            ->where('s.site_id', $this->site_id)
            ->fieldRaw("
                s.store_id,
                s.store_name,
                s.headimg,
                COUNT(CASE WHEN o.order_status = 'finish' {$timeCondition} THEN o.order_id END) AS service_order_count,
                IFNULL(SUM(CASE WHEN o.order_status = 'finish' {$timeCondition} THEN o.pay_money ELSE 0 END), 0) AS total_order_money,
                IFNULL(SUM(CASE WHEN o.order_status = 'finish' {$timeCondition} THEN (o.technician_commission + o.technician_additional_commission) ELSE 0 END), 0) AS total_commission,
                SUM(CASE WHEN o.dispatch_timeout_time > 0 {$timeCondition} THEN 1 ELSE 0 END) AS timeout_count
            ")
            ->limit(10)
            ->group('store_id')
            ->order($orderField, 'desc')->select()->toArray();

        $storeIds = array_column($list,'store_id');
        $evaluateTimeStatsMap = (new CoreEvaluateService)->batchGetTimeRangeStats($this->site_id, 'store_id', $storeIds, 0, $timeRange[0], $timeRange[1]);
        foreach ($list as &$value){
            $value['evaluate_avg_scores'] = 0;
            if (isset($evaluateTimeStatsMap[$value['store_id']])){
                $total_evaluate_count = $evaluateTimeStatsMap[$value['store_id']]['total_evaluate_count'];
                $total_scores = $evaluateTimeStatsMap[$value['store_id']]['total_scores'];
                if($total_evaluate_count >0 && $total_scores > 0){
                    $value['evaluate_avg_scores'] = round($total_scores / $total_evaluate_count, 1);
                }
            }
        }
        return $list;
    }

     /**
      * 财务报表-收入趋势
      **/
    public function getFinanceIncomeTrendStats($where)
    {
        return $this->getIncomeTrendStats($where, "
            pay_money
            - IFNULL(store_commission,0)
            - IFNULL(store_additional_commission,0)
            - IFNULL(technician_commission,0)
            - IFNULL(technician_additional_commission,0)
        ");
    }

    /**
     * 财务报表-门店收入趋势
     **/
    public function getFinanceStoreIncomeTrendStats($where)
    {
        return $this->getIncomeTrendStats($where, "
            IFNULL(store_commission,0) + IFNULL(store_additional_commission,0)
        ");
    }

    /**
     * 财务报表-师傅收入趋势
     **/
    public function getFinanceTechnicianIncomeTrendStats($where)
    {
        return $this->getIncomeTrendStats($where, "
            IFNULL(technician_commission,0) + IFNULL(technician_additional_commission,0)
        ");
    }

    /**
     * 通用：财务报表 - 收入趋势
     * @param array $where 筛选条件（含 date_type / date）
     * @param string $incomeExpr SQL表达式（决定收入计算方式）
     */
    public function getIncomeTrendStats($where, string $incomeExpr)
    {
        // === 1. 时间范围 & 维度 ===
        switch ($where['date_type']) {
            case 'week':
                $startTime = strtotime('monday this week');
                $endTime = strtotime('sunday this week 23:59:59');
                $groupFormat = '%Y-%m-%d';
                $interval = 'day';
                break;

            case 'month':
                $startTime = strtotime(date('Y-m-01 00:00:00'));
                $endTime = strtotime(date('Y-m-t 23:59:59'));
                $groupFormat = '%Y-%m-%d';
                $interval = 'day';
                break;

            case 'year':
                $startTime = strtotime(date('Y-01-01 00:00:00'));
                $endTime = strtotime(date('Y-12-31 23:59:59'));
                $groupFormat = '%Y-%m';
                $interval = 'month';
                break;

            case 'custom':
                if (empty($where['date']) || count($where['date']) !== 2) {
                    throw new AdminException('自定义时间范围不能为空');
                }
                [$startTime, $endTime] = $where['date'];
                $startTime = strtotime($startTime.'00:00:00');
                $endTime = strtotime($endTime.'23:59:59');
                $days = ceil(($endTime - $startTime) / 86400);
                if ($days <= 31) {
                    $groupFormat = '%Y-%m-%d';
                    $interval = 'day';
                } else {
                    $groupFormat = '%Y-%m';
                    $interval = 'month';
                }
                break;

            default:
                throw new AdminException('筛选类型错误');
        }
        // === 2. 查询 ===
        $query = (new Order())
            ->where([['site_id', '=', $this->site_id]])
            ->where(function ($query) {
                $query->where('order_status', '=', OrderDict::FINISH)
                    ->whereOr('refund_status', '=', RefundDict::REFUND_COMPLETED);
            })
            ->whereBetween('finish_time', [$startTime, $endTime]);


        $list = $query->field([
            "DATE_FORMAT(FROM_UNIXTIME(finish_time), '{$groupFormat}') as date",
            Db::raw("SUM({$incomeExpr}) as total_income")
        ])
            ->group('date')
            ->order('date asc')
            ->select()
            ->toArray();

        // === 3. 转 map ===
        $dataMap = [];
        foreach ($list as $row) {
            $dataMap[$row['date']] = number_format($row['total_income'], 2, '.', '');
        }

        // === 4. 时间轴补零 ===
        $xAxis = [];
        $series = [];
        if ($interval === 'day') {
            for ($t = $startTime; $t <= $endTime; $t += 86400) {
                $d = date('Y-m-d', $t);
                $xAxis[] = $d;
                $series[] = $dataMap[$d] ?? 0;
            }
        } else {
            $current = strtotime(date('Y-m-01', $startTime));
            $end = strtotime(date('Y-m-01', $endTime));
            while ($current <= $end) {
                $d = date('Y-m', $current);
                $xAxis[] = $d;
                $series[] = $dataMap[$d] ?? 0;
                $current = strtotime('+1 month', $current);
            }
        }

        return [
            'xAxis' => $xAxis,
            'series' => $series,
        ];
    }

    /**
     * 财务报表-售后支出趋势
     **/
    public function getRefundExpenditureStats($where)
    {
        // === 1. 时间范围 & 维度 ===
        switch ($where['date_type']) {
            case 'week':
                $startTime = strtotime('monday this week');
                $endTime = strtotime('sunday this week 23:59:59');
                $groupFormat = '%Y-%m-%d';
                $interval = 'day';
                break;

            case 'month':
                $startTime = strtotime(date('Y-m-01 00:00:00'));
                $endTime = strtotime(date('Y-m-t 23:59:59'));
                $groupFormat = '%Y-%m-%d';
                $interval = 'day';
                break;

            case 'year':
                $startTime = strtotime(date('Y-01-01 00:00:00'));
                $endTime = strtotime(date('Y-12-31 23:59:59'));
                $groupFormat = '%Y-%m';
                $interval = 'month';
                break;

            case 'custom':
                if (empty($where['date']) || count($where['date']) !== 2) {
                    throw new AdminException('自定义时间范围不能为空');
                }
                [$startTime, $endTime] = $where['date'];
                $startTime = strtotime($startTime.'00:00:00');
                $endTime = strtotime($endTime.'23:59:59');
                $days = ceil(($endTime - $startTime) / 86400);
                if ($days <= 31) {
                    $groupFormat = '%Y-%m-%d';
                    $interval = 'day';
                } else {
                    $groupFormat = '%Y-%m';
                    $interval = 'month';
                }
                break;

            default:
                throw new AdminException('筛选类型错误');
        }

        // === 2. 查询 ===
        $query = (new OrderRefund())
            ->where([['site_id', '=', $this->site_id], ['status', '=', RefundDict::REFUND_COMPLETED]])
            ->whereBetween('transfer_time', [$startTime, $endTime]);

        $list = $query->field([
            "DATE_FORMAT(FROM_UNIXTIME(transfer_time), '{$groupFormat}') as date",
            Db::raw("SUM(money) as total_income")
        ])
            ->group('date')
            ->order('date asc')
            ->select()
            ->toArray();

        // === 3. 转 map ===
        $dataMap = [];
        foreach ($list as $row) {
            $dataMap[$row['date']] = number_format($row['total_income'], 2, '.', '');
        }

        // === 4. 时间轴补零 ===
        $xAxis = [];
        $series = [];
        if ($interval === 'day') {
            for ($t = $startTime; $t <= $endTime; $t += 86400) {
                $d = date('Y-m-d', $t);
                $xAxis[] = $d;
                $series[] = $dataMap[$d] ?? 0;
            }
        } else {
            $current = strtotime(date('Y-m-01', $startTime));
            $end = strtotime(date('Y-m-01', $endTime));
            while ($current <= $end) {
                $d = date('Y-m', $current);
                $xAxis[] = $d;
                $series[] = $dataMap[$d] ?? 0;
                $current = strtotime('+1 month', $current);
            }
        }

        return [
            'xAxis' => $xAxis,
            'series' => $series,
        ];
    }

    /**
     * 客户报表
     **/
    public function getReceiptExpenditureAnalyze($where)
    {
        // === 1. 时间维度与分组格式 ===
        switch ($where['date_type']) {
            case 'week':
                $startTime = strtotime('monday this week');
                $endTime = strtotime('sunday this week 23:59:59');
                $groupFormat = '%Y-%m-%d';
                $interval = 'day';
                break;

            case 'month':
                $startTime = strtotime(date('Y-m-01 00:00:00'));
                $endTime = strtotime(date('Y-m-t 23:59:59'));
                $groupFormat = '%Y-%m-%d';
                $interval = 'day';
                break;

            case 'year':
                $startTime = strtotime(date('Y-01-01 00:00:00'));
                $endTime = strtotime(date('Y-12-31 23:59:59'));
                $groupFormat = '%Y-%m';
                $interval = 'month';
                break;

            case 'custom':
                if (empty($where['date']) || count($where['date']) !== 2) {
                    throw new AdminException('自定义时间范围不能为空');
                }
                [$startTime, $endTime] = $where['date'];
                $startTime = strtotime($startTime.'00:00:00');
                $endTime = strtotime($endTime.'23:59:59');
                $days = ceil(($endTime - $startTime) / 86400);
                if ($days <= 31) {
                    $groupFormat = '%Y-%m-%d';
                    $interval = 'day';
                } else {
                    $groupFormat = '%Y-%m';
                    $interval = 'month';
                }
                break;

            default:
                throw new AdminException('筛选类型错误');
        }

        // === 2. 基础 where 条件 ===
        $orderWhere = [
            ['site_id', '=', $this->site_id],
            ['order_status', '=', OrderDict::FINISH],
            ['finish_time', 'between', [$startTime, $endTime]],
        ];

        $refundWhere = [
            ['site_id', '=', $this->site_id],
            ['status', '=', RefundDict::REFUND_COMPLETED],
            ['transfer_time', 'between', [$startTime, $endTime]]
        ];

        // === 3. 查询订单主表 ===
        $orderList = (new Order())
            ->where($orderWhere)
            ->field([
                "DATE_FORMAT(FROM_UNIXTIME(finish_time), '{$groupFormat}') as date",
                "COUNT(order_id) as order_count",
                "SUM(pay_money) as total_income",
                "SUM(
                IFNULL(store_commission,0)
                + IFNULL(store_additional_commission,0)
                + IFNULL(technician_commission,0)
                + IFNULL(technician_additional_commission,0)
            ) as total_commission"
            ])
            ->group('date')
            ->order('date asc')
            ->select()
            ->toArray();
        // === 4. 查询退款表 ===
        $refundList = (new OrderRefund())
            ->where($refundWhere)
            ->field([
                "DATE_FORMAT(FROM_UNIXTIME(transfer_time), '{$groupFormat}') as date",
                "SUM(money) as refund_money"
            ])
            ->group('date')
            ->select()
            ->toArray();

        // === 5. 数据映射 ===
        $orderMap = [];
        foreach ($orderList as $row) {
            $orderMap[$row['date']] = [
                'order_count' => (int)$row['order_count'],
                'total_income' => round($row['total_income'], 2),
                'total_commission' => round($row['total_commission'], 2),
            ];
        }

        $refundMap = [];
        foreach ($refundList as $row) {
            $refundMap[$row['date']] = round($row['refund_money'], 2);
        }

        // === 6. 生成完整时间轴 & 汇总 ===
        $data = [];

        if ($interval === 'day') {
            for ($t = $startTime; $t <= $endTime; $t += 86400) {
                $d = date('Y-m-d', $t);
                $orderData = $orderMap[$d] ?? ['order_count' => 0, 'total_income' => 0, 'total_commission' => 0];
                $refund = $refundMap[$d] ?? 0;
                $data[] = [
                    'date' => $d,
                    'order_count' => $orderData['order_count'],
                    'total_income' => number_format($orderData['total_income'], 2, '.', ''),
                    'refund_money' => number_format($refund, 2, '.', ''),
                    'total_commission' => number_format($orderData['total_commission'], 2, '.', ''),
                    'platform_income' => number_format($orderData['total_income'] - $orderData['total_commission'], 2, '.', '')
                ];
            }
        } else { // 月维度
            $current = strtotime(date('Y-m-01', $startTime));
            $end = strtotime(date('Y-m-01', $endTime));
            while ($current <= $end) {
                $d = date('Y-m', $current);
                $orderData = $orderMap[$d] ?? ['order_count' => 0, 'total_income' => 0, 'total_commission' => 0];
                $refund = $refundMap[$d] ?? 0;
                $data[] = [
                    'date' => $d,
                    'order_count' => $orderData['order_count'],
                    'total_income' => number_format($orderData['total_income'], 2, '.', ''),
                    'refund_money' => number_format($refund, 2, '.', ''),
                    'total_commission' => number_format($orderData['total_commission'], 2, '.', ''),
                    'platform_income' => number_format($orderData['total_income'] - $orderData['total_commission'], 2, '.', '')
                ];
                $current = strtotime('+1 month', $current);
            }
        }

        return $data;
    }

    public function getMemberStats($where)
    {

        switch ($where['date_type']) {
            case 'week':
                $startTime = strtotime('monday this week');
                $endTime = strtotime('sunday this week 23:59:59');
                break;

            case 'month':
                $startTime = strtotime(date('Y-m-01 00:00:00'));
                $endTime = strtotime(date('Y-m-t 23:59:59'));
                break;

            case 'year':
                $startTime = strtotime(date('Y-01-01 00:00:00'));
                $endTime = strtotime(date('Y-12-31 23:59:59'));
                break;

            case 'custom':
                if (empty($where['date']) || count($where['date']) !== 2) {
                    throw new AdminException('自定义时间范围不能为空');
                }
                [$startTime, $endTime] = $where['date'];
                $startTime = strtotime($startTime.'00:00:00');
                $endTime = strtotime($endTime.'23:59:59');
                break;

            default:
                throw new AdminException('筛选类型错误');
        }

        // 基础查询
        $search_model = (new Order())
            ->field([
                'member_id',
                'COUNT(order_id) as total_orders',
                "SUM(CASE WHEN order_status = 'finish' THEN 1 ELSE 0 END) as completed_orders",
                "SUM(CASE WHEN order_status = 'wait_check' THEN 1 ELSE 0 END) as wait_check_orders",
                "SUM(CASE WHEN order_status = 'finish' THEN pay_money ELSE 0 END) as completed_amount",
                "SUM(CASE WHEN refund_status = 'refund_completed' THEN 1 ELSE 0 END) as completed_refund_orders",
            ])
            ->with(['member' => function($query){
                $query->field('member_id,nickname');
            }])
            ->where([['site_id', '=', $this->site_id]])
            ->whereBetween('create_time', [$startTime,$endTime])
            ->group('member_id');

        $list = $this->pageQuery($search_model);
        $total_money = 0;
        if(!empty($list['data'])){
            foreach ($list['data'] as $value){
                $total_money += $value['completed_amount'];
            }
        }
        $list['total_money'] = number_format((float)$total_money, 2, '.', '');

        return $list;
    }
}
