<?php

namespace addon\home_service\app\service\api\store;

use addon\home_service\app\dict\account\AccountDict;
use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\dict\order\RefundDict;
use addon\home_service\app\dict\order\StoreOrderDict;
use addon\home_service\app\dict\store\StoreStatDict;
use addon\home_service\app\dict\technician\TechnicianDict;
use addon\home_service\app\model\account\StoreAccount;
use addon\home_service\app\model\order\Order;
use addon\home_service\app\model\technician\Technician;
use addon\home_service\app\model\technician\TechnicianRest;
use core\base\BaseApiService;
use core\exception\ApiException;
use think\facade\Db;


/**
 * 门店统计业务
 */
class StatisticsService extends BaseApiService
{

    use StoreTrait;


    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
        Order::$contextRole = Order::ROLE_TECHNICIAN;
        $this->checkStore();
    }


    public function __destruct()
    {
        Order::$contextRole = null;
    }


    /**
     *今日数据
     */
    public function getTodayData()
    {
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        $todayEnd   = strtotime(date('Y-m-d 23:59:59'));

        $fields = [
            'COUNT(order_id) as today_completed_count',  // 今天已完成订单总数
            // 计算总佣金（基础佣金+额外佣金），保留2位小数确保精度
            'ROUND(SUM(IFNULL(store_commission,0) + IFNULL(store_additional_commission,0)), 2) as store_sum_commission'
        ];
        $baseWhere = [
            ['site_id', '=', $this->site_id],
            ['store_id', '=', $this->store_id],
            function ($query) {
                $query->where('order_status', '=', OrderDict::FINISH)
                    ->whereOr('refund_status', '=', RefundDict::REFUND_COMPLETED);
            }
        ];
        $stats = $this->model->where($baseWhere)
            ->whereBetween('create_time', [$todayStart, $todayEnd])  // 直接传时间戳数组
            ->field($fields)
            ->find();
        // 转换结果类型，确保数据类型正确
        return [
            'today_completed_count' => (int)($stats['today_completed_count'] ?? 0),
            'total_commission' => (float)($stats['store_sum_commission'] ?? 0.00)
        ];
    }


    /**
     *订单看板
     */
    public function getOrderDashboard()
    {
        $baseWhere = [
            ['site_id', '=', $this->site_id],
            ['pay_time', '>', 0],
            ['store_id', '=', $this->store_info['store_id']],
        ];
        // 构建包含条件判断的SQL字段
        $fields = [
            // 待派单数
            'SUM(CASE WHEN order_status = "' . StoreOrderDict::WAIT_DISPATCH . '" 
                 AND refund_status = "" 
                 THEN 1 ELSE 0 END) as wait_dispatch_count',
            // 待服务数
            'SUM(CASE WHEN order_status = "' . StoreOrderDict::WAIT_SERVICE . '" 
                 AND refund_status = "" 
                 THEN 1 ELSE 0 END) as wait_service_count',
            // 服务中数
            'SUM(CASE WHEN order_status = "' . StoreOrderDict::IN_SERVICE . '" 
                 AND refund_status = "" 
                 THEN 1 ELSE 0 END) as in_service_count',
            // 待验收数
            'SUM(CASE WHEN order_status = "' . StoreOrderDict::WAIT_CHECK . '" 
                 AND refund_status = "" 
                 THEN 1 ELSE 0 END) as wait_check_count',
            // 已完成数
            'SUM(CASE WHEN order_status = "' . StoreOrderDict::FINISH . '" 
                 AND refund_status = "" 
                 THEN 1 ELSE 0 END) as finish_count',
            // 售后数
            'SUM(CASE WHEN  refund_status != "" 
                 THEN 1 ELSE 0 END) as refund_count',
            // 已关闭数
            'SUM(CASE WHEN order_status = "' . StoreOrderDict::CLOSE . '" 
                 THEN 1 ELSE 0 END) as close_count'
        ];
        // 执行查询
        $stats = $this->model->where($baseWhere)
            ->field($fields)
            ->find()->toArray();
        return $stats;
    }

    /**
     * 师傅动态
     */
    public function getTechnicianDynamic()
    {
        $offline_count = 0;
        $technician_list = (new Technician())
            ->field('id,status')
            ->where([
                ['site_id', '=', $this->site_id],
                ['store_id', '=', $this->store_id],
            ])
            ->select()->toArray();
        $total = count($technician_list);
        $technician_ids = array_column($technician_list,'id');
        if ($total === 0) {
            return ['total' => 0, 'in_service_count' => 0, 'free_count' => 0, 'offline_count' => 0];
        }

        foreach ($technician_list as $key=>$value){
            if ($value['status'] == TechnicianDict::OFF){
                unset($technician_ids[$key]);
                $offline_count += 1;
            }
        }

        // 服务中师傅人数
        $in_service_count = (new Order())
            ->where([
                ['site_id', '=', $this->site_id],
                ['store_id', '=', $this->store_id],
                ['technician_id', 'in', $technician_ids],
            ])
            ->whereNotIn('order_status', ['close','finish'])
            ->group('technician_id')
            ->count();

        $free_count = $total - $offline_count - $in_service_count;

        return [
            'total' => $total,
            'in_service_count' => $in_service_count,
            'free_count' => $free_count,
            'offline_count' => $offline_count,
        ];
    }

    /**
     * 判断师傅是否在休息（基于当前半小时时间点）
     *
     * @param int $siteId
     * @param int $technicianId
     * @param \DateTime|null $now
     * @return bool
     */
    public function isTechnicianOffline(int $siteId, int $technicianId, ?\DateTime $now = null): bool
    {
        $now = $now ?: new \DateTime();
        $date = $now->format('Y-m-d');

        // 1. 把当前时间对齐到半小时 slot
        $minute = intval($now->format('i'));
        $hour   = $now->format('H');

        if ($minute < 30) {
            $slot = sprintf("%02d:00", $hour);
        } else {
            $slot = sprintf("%02d:30", $hour);
        }

        // 2. 查询当天的休息记录
        $row = (new TechnicianRest())->where([
            ['site_id', '=', $this->site_id],
            ['store_id', 'in', [0,$this->store_id]],
            ['technician_id', '=', $technicianId],
            ['date', '=', $date],
        ])->findOrEmpty()->toArray();

        if (!$row) return false; // 没休息记录 → 不离线

        $hours = explode(',', $row['hour']);
        $hours = array_map('trim', $hours);

        // 3. 当前 slot 在休息集合 → 离线
        return in_array($slot, $hours, true);
    }

    /**
     * 账单收支统计图
     */
    public function getIncomeAndExpenseStatChart(array $where)
    {
        $dateType = $where['date_type'] ?? 'day';

        $query = (new StoreAccount())
            ->fieldRaw("
            CASE
                WHEN '{$dateType}' = 'day' THEN FROM_UNIXTIME(create_time, '%Y-%m-%d')
                ELSE DATE_FORMAT(FROM_UNIXTIME(create_time), '%Y-%m')
            END AS time_key,
            SUM(CASE WHEN from_type IN ('order_commission','order_refund_commission') THEN account_data ELSE 0 END) AS income,
            SUM(CASE WHEN from_type='cash_out' THEN account_data ELSE 0 END) AS expense
        ")
            ->where([
                ['site_id', '=', $this->site_id],
                ['store_id', '=', $this->store_id],
                ['status', '=', 1],
            ]);

        if ($dateType === 'day') {
            $query->where('create_time', '>=', strtotime('-6 days'))
                ->where('create_time', '<=', time());
        } elseif ($dateType === 'month') { // month
            $yearStart = strtotime(date('Y-01-01'));
            $yearEnd = strtotime((date('Y') + 1) . '-01-01');
            $query->whereBetween('create_time', [$yearStart, $yearEnd]);
        } else {
            throw new ApiException('UNSUPPORTED_TIME_TYPE');
        }

        $data = $query->group('time_key')->order('time_key ASC')->select()->toArray();

        // 根据时间类型生成 xAxis 和 series
        return $this->formatChartData($data, $dateType);
    }

    /**
     * 账单收支统计图
     */
    public function getIncomeAndExpenseStat(array $where)
    {
        $query = (new StoreAccount())
            ->fieldRaw("
                SUM(CASE WHEN from_type IN ('order_commission','order_refund_commission') THEN account_data ELSE 0 END) AS income,
                SUM(CASE WHEN from_type='cash_out' THEN account_data ELSE 0 END) AS expense
            ");

        // 时间范围
        if ($where['date_type'] === 'day') {
            $start = strtotime('today 00:00:00');
            $end = strtotime('today 23:59:59');
        } elseif ($where['date_type'] === 'month') {
            $start = strtotime(date('Y-m-01 00:00:00'));
            $end = strtotime(date('Y-m-t 23:59:59'));
        } else {
            throw new ApiException('UNSUPPORTED_TIME_TYPE');
        }

        $query->where([
            ['site_id', '=', $this->site_id],
            ['store_id', '=', $this->store_id],
            ['status', '=', 1],
        ])->whereBetween('create_time', [$start, $end]);

        $data = $query->find();

        return [
            'income' => isset($data['income']) ? (float)$data['income'] : 0.00,
            'expense' => isset($data['expense']) ? (float)$data['expense'] : 0.00,
        ];
    }

    /**
     * 格式化柱状图数据
     */
    protected function formatChartData(array $data, string $dateType): array
    {
        $xAxis = [];
        $incomeSeries = [];
        $expenseSeries = [];

        if ($dateType === 'day') {
            for ($i = 6; $i >= 0; $i--) {
                $fullDate = date('Y-m-d', strtotime("-$i days"));
                $xAxis[] = date('j日', strtotime($fullDate)); // 只显示日期 8日、9日
                [$income, $expense] = $this->findDataByKey($data, $fullDate);
                $incomeSeries[] = $income;
                $expenseSeries[] = abs($expense);
            }
        } else { // month
            for ($m = 1; $m <= 12; $m++) {
                $monthStr = date('Y-m', strtotime(date('Y') . "-$m-01"));
                $xAxis[] = $m . '月';
                [$income, $expense] = $this->findDataByKey($data, $monthStr);
                $incomeSeries[] = $income;
                $expenseSeries[] = abs($expense);
            }
        }

        return [
            'xAxis' => $xAxis,
            'series' => [
                'income' => $incomeSeries,
                'expense' => $expenseSeries,
            ],
        ];
    }

    /**
     * 根据 time_key 查找收入和支出
     */
    protected function findDataByKey($data, $key)
    {
        foreach ($data as $row) {
            if ($row['time_key'] === $key) {
                return [(float)$row['income'], (float)$row['expense']];
            }
        }
        return [0, 0]; // 没有数据返回 0
    }

    /**
     * 日账单统计
     */
    public function getDayBillStat()
    {
        $start_time = strtotime(date('Y-m-d 00:00:00'));
        $end_time = strtotime(date('Y-m-d 23:59:59'));

        $baseWhere = [
            ['site_id', '=', $this->site_id],
            ['store_id', '=', $this->store_id],
        ];
        $order_commission = AccountDict::ORDER_COMMISSION;
        $order_refund_commission = AccountDict::ORDER_REFUND_COMMISSION;
        $cash_out = AccountDict::CASH_OUT;
        $result = (new StoreAccount())
            ->fieldRaw("
                SUM(CASE WHEN from_type IN ('{$order_commission}','{$order_refund_commission}') 
                          AND create_time BETWEEN {$start_time} AND {$end_time} 
                         THEN account_data ELSE 0 END) AS income,
                SUM(CASE WHEN from_type = '{$cash_out}' 
                          AND create_time BETWEEN {$start_time} AND {$end_time} 
                         THEN account_data ELSE 0 END) AS expense
            ")
            ->where($baseWhere)
            ->find();

        return [
            'today_income_count' => (float)($result['income'] ?? 0.00),
            'total_expense' => abs((float)($result['expense'] ?? 0.00)),
        ];
    }

    /**
     *日订单统计
     */
    public function getDayOrderStat()
    {
        $start_time = strtotime(date('Y-m-d 00:00:00'));
        $end_time = strtotime(date('Y-m-d 23:59:59'));

        $baseWhere = [
            ['site_id', '=', $this->site_id],
            ['store_id', '=', $this->store_id],
        ];

        $wait_service = StoreOrderDict::WAIT_SERVICE;
        $wait_check = StoreOrderDict::WAIT_CHECK;
        $finish = StoreOrderDict::FINISH;

        return $this->model->fieldRaw("
                SUM(CASE WHEN order_status = '{$wait_service}' 
                          AND create_time BETWEEN {$start_time} AND {$end_time} 
                         THEN 1 ELSE 0 END) AS wait_service_count,
                SUM(CASE WHEN order_status = '{$wait_check}' 
                          AND service_finish_time BETWEEN {$start_time} AND {$end_time} 
                         THEN 1 ELSE 0 END) AS wait_check_count,
                SUM(CASE WHEN order_status = '{$finish}' 
                          AND finish_time BETWEEN {$start_time} AND {$end_time} 
                         THEN 1 ELSE 0 END) AS finish_count
            ")
            ->where($baseWhere)
            ->find();
    }

    /**
     * 月订单统计
     */
    public function getMonthOrderStat($where)
    {
        // 如果没传日期，默认当前月份
        $date = !empty($where['date']) ? $where['date'] : date('Y-m');
        $start_time = strtotime($date . '-01 00:00:00');
        $end_time = strtotime(date('Y-m-t 23:59:59', strtotime($date)));

        $baseWhere = [
            ['site_id', '=', $this->site_id],
            ['store_id', '=', $this->store_id],
        ];

        $result = $this->model->fieldRaw("
            SUM(CASE 
                WHEN order_status = 'finish' 
                 AND create_time BETWEEN {$start_time} AND {$end_time} 
                THEN 1 ELSE 0 END) AS finish_count,
    
            SUM(CASE 
                WHEN order_status = 'close' 
                 AND close_time BETWEEN {$start_time} AND {$end_time} 
                THEN 1 ELSE 0 END) AS close_count,
    
            SUM(CASE 
                WHEN refund_status <> '' 
                 AND refund_apply_time BETWEEN {$start_time} AND {$end_time} 
                THEN 1 ELSE 0 END) AS refund_count
        ")->where($baseWhere)->find();
        return $result;
    }

    /**
     * 获取订单明细列表
     */
    public function getOrderPage($where)
    {
        $start_time = $end_time = '';
        if ($where['date_type'] == 'day') {
            $start_time = strtotime(date('Y-m-d 00:00:00'));
            $end_time = strtotime(date('Y-m-d 23:59:59'));
        } elseif ($where['date_type'] == 'month') {
            $date = !empty($where['date']) ? $where['date'] : date('Y-m');
            $start_time = strtotime($date . '-01 00:00:00');
            $end_time = strtotime(date('Y-m-t 23:59:59', strtotime($date)));
        }
        if (empty($start_time) || empty($end_time)) return [];

        $search_model = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['store_id', '=', $this->store_id],
        ]);

        $status_name = '';
        switch ($where['status']) {
            case StoreStatDict::FINISH: // 已完成
                $search_model->where([['order_status', '=', OrderDict::FINISH], ['create_time', 'between', [$start_time, $end_time]]]);
                $status_name = StoreStatDict::getStoreStatOrderStatus(StoreStatDict::FINISH);
                break;
            case StoreStatDict::REFUND: // 退款/售后
                $search_model->where([['order_status', '<>', OrderDict::CLOSE], ['refund_status', '<>', ''], ['refund_apply_time', 'between', [$start_time, $end_time]]]);
                $status_name = StoreStatDict::getStoreStatOrderStatus(StoreStatDict::REFUND);
                break;
            case StoreStatDict::CLOSE: // 已关闭
                $search_model->where([['order_status', '=', OrderDict::CLOSE], ['close_time', 'between', [$start_time, $end_time]]]);
                $status_name = StoreStatDict::getStoreStatOrderStatus(StoreStatDict::CLOSE);
                break;
            default:
                break;
        }
        $search_model = $search_model
            ->order('create_time desc')
            ->field('order_id, order_no, order_name, create_time, order_status, taker_address, taker_full_address');

        return $this->pageQuery($search_model, function ($item) use ($status_name) {
            $item['taker_full_address'] = $item['taker_full_address'] . $item['taker_address'];
            $item['status_name'] = $status_name;

        });
    }


}
