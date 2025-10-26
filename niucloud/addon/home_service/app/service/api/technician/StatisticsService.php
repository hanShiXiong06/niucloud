<?php

namespace addon\home_service\app\service\api\technician;

use addon\home_service\app\dict\account\AccountDict;
use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\dict\order\RefundDict;
use addon\home_service\app\dict\order\TechnicianOrderDict;
use addon\home_service\app\dict\technician\TechnicianStatDict;
use addon\home_service\app\model\account\TechnicianAccount;
use addon\home_service\app\model\order\Order;
use core\base\BaseApiService;
use core\exception\ApiException;
use think\facade\Db;


/**
 * 师傅统计业务
 */
class StatisticsService extends BaseApiService
{

    use TechnicianTrait;


    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
        Order::$contextRole = Order::ROLE_TECHNICIAN;
        $this->checkTechnician();
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
        $fields = [
            'COUNT(order_id) as today_completed_count',  // 今天已完成订单总数
            // 计算总佣金（基础佣金+额外佣金），保留2位小数确保精度
            'SUM(IFNULL(technician_commission, 0) + IFNULL(technician_additional_commission, 0)) AS total_commission'
        ];
        $baseWhere = [
            ['site_id', '=', $this->site_id],
            ['technician_id', '=', $this->technician_id],
            function ($query) {
                $query->where('order_status', '=', OrderDict::FINISH)
                    ->whereOr('refund_status', '=', RefundDict::REFUND_COMPLETED);
            }
        ];
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        $todayEnd = strtotime(date('Y-m-d 23:59:59'));

        $stats = $this->model->where($baseWhere)
            ->whereBetween('create_time', [$todayStart, $todayEnd])  // 直接传时间戳数组
            ->field($fields)
            ->find();
        // 转换结果类型，确保数据类型正确
        return [
            'today_completed_count' => (int)($stats['today_completed_count'] ?? 0),
            'total_commission' => $stats['total_commission'] ?? 0.00
        ];
    }

    /**
     * 账单收支统计图
     */
    public function getIncomeAndExpenseStatChart(array $where)
    {
        $dateType = $where['date_type'] ?? 'day';

        $query = (new TechnicianAccount())
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
                ['technician_id', '=', $this->technician_id],
                ['status', '=', 1],
            ]);

        if ($dateType === 'day') {
            $query->where('create_time', '>=', strtotime('-6 days'))
                ->where('create_time', '<=', time());
        } elseif ($dateType === 'month') { // month
            $yearStart = strtotime(date('Y-01-01'));
            $yearEnd = strtotime((date('Y')+1) . '-01-01');
            $query->whereBetween('create_time', [$yearStart, $yearEnd]);
        }else{
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
        $query = (new TechnicianAccount())
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
            ['technician_id', '=', $this->technician_id],
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
            ['technician_id', '=', $this->technician_id],
        ];
        $order_commission = AccountDict::ORDER_COMMISSION;
        $order_refund_commission = AccountDict::ORDER_REFUND_COMMISSION;
        $cash_out = AccountDict::CASH_OUT;
        $result = (new TechnicianAccount())
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
            ['technician_id', '=', $this->technician_id],
        ];

        $wait_service = TechnicianOrderDict::WAIT_SERVICE;
        $wait_check = TechnicianOrderDict::WAIT_CHECK;
        $finish = TechnicianOrderDict::FINISH;

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
        $end_time   = strtotime(date('Y-m-t 23:59:59', strtotime($date)));

        $baseWhere = [
            ['site_id', '=', $this->site_id],
            ['technician_id', '=', $this->technician_id],
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
        if($where['date_type'] == 'day'){
            $start_time = strtotime(date('Y-m-d 00:00:00'));
            $end_time = strtotime(date('Y-m-d 23:59:59'));
        }elseif ($where['date_type'] == 'month'){
            $date = !empty($where['date']) ? $where['date'] : date('Y-m');
            $start_time = strtotime($date . '-01 00:00:00');
            $end_time   = strtotime(date('Y-m-t 23:59:59', strtotime($date)));
        }
        if (empty($start_time) || empty($end_time)) return [];

        $search_model = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['technician_id', '=', $this->technician_id],
        ]);

        $status_name = '';
        switch ($where['status']) {
            case TechnicianStatDict::FINISH: // 已完成
                $search_model->where([['order_status', '=', OrderDict::FINISH], ['create_time', 'between', [$start_time,$end_time]]]);
                $status_name = TechnicianStatDict::getTechnicianStatOrderStatus(TechnicianStatDict::FINISH);
                break;
            case TechnicianStatDict::REFUND: // 退款/售后
                $search_model->where([['order_status', '<>', OrderDict::CLOSE], ['refund_status', '<>', ''], ['refund_apply_time', 'between', [$start_time,$end_time]]]);
                $status_name = TechnicianStatDict::getTechnicianStatOrderStatus(TechnicianStatDict::REFUND);
                break;
            case TechnicianStatDict::CLOSE: // 已取消
                $search_model->where([['order_status', '=', OrderDict::CLOSE], ['close_time', 'between', [$start_time,$end_time]]]);
                $status_name = TechnicianStatDict::getTechnicianStatOrderStatus(TechnicianStatDict::CLOSE);
                break;
            default:
                break;
        }
        $search_model =  $search_model
            ->order('create_time desc')
            ->field('order_id, order_no, order_name, create_time, order_status, taker_address, taker_full_address');

        return $this->pageQuery($search_model,function($item) use ($status_name){
            $item['taker_full_address'] = $item['taker_full_address'] . $item['taker_address'];
            $item['status_name'] = $status_name;

        });
    }


    /**
     *获取师傅门店排行榜
     */
    public function getRank($where)
    {
        if (empty($where['type']) || !in_array($where['type'], ['region','store']))  return [];

        $model = (new TechnicianAccount());
        $start_time = strtotime(date('Y-m-01 00:00:00'));
        $end_time   = strtotime(date('Y-m-01 23:59:59', strtotime('+1 month')));

        $base_where = [
            ['technician_account.site_id', '=', $this->site_id],
            ['technician_account.category_id', '=', $where['category_id']],
            ['technician_account.status', '=', 1],
        ];
        $technician_where = [];
        if ($where['type'] == 'region') {
            if (empty($this->technician_info['city_id'])) return [];
            $technician_where[] = ['city_id', '=', $this->technician_info['city_id']];
        }elseif ($where['type'] == 'store'){
            if (empty($this->technician_info['store_id'])) return [];
            $technician_where[] = ['store_id', '=', $this->technician_info['store_id']];
        }

        return $model
            ->field([
                'site_id',
                'technician_id',
                'total_amount' => Db::raw('SUM(account_data) as total_amount')
            ])
            ->withJoin([
                'technician' => function($query) use ($technician_where) {
                    $query->field('real_name as name, headimg')->where($technician_where);
                }
            ], 'LEFT')
            ->where($base_where)
            ->whereBetween('payment_time', [$start_time, $end_time])
            ->hidden(['technician'])
            ->group('technician_id')
            ->order('total_amount desc')
            ->limit(10)
            ->append(['headimg_mid'])
            ->select()
            ->toArray();
    }
}
