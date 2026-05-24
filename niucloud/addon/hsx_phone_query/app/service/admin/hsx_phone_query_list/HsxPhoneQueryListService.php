<?php
namespace addon\hsx_phone_query\app\service\admin\hsx_phone_query_list;

use addon\hsx_phone_query\app\dict\HsxPhoneQueryOrderDict;
use addon\hsx_phone_query\app\model\HsxPhoneQueryApiLog;
use addon\hsx_phone_query\app\model\HsxPhoneQueryInfo;
use addon\hsx_phone_query\app\model\HsxPhoneQueryOrder;
use addon\hsx_phone_query\app\service\core\report\QueryResultFormatter;
use core\base\BaseAdminService;

/**
 * 查询记录管理服务层
 */
class HsxPhoneQueryListService extends BaseAdminService
{
    private QueryResultFormatter $resultFormatter;

    public function __construct()
    {
        parent::__construct();
        $this->resultFormatter = new QueryResultFormatter();
    }

    /**
     * 获取查询记录列表
     */
    public function getList($params = [])
    {
        // 明确指定主表字段
        $field = [
            'hsx_phone_query_info.id', 
            'hsx_phone_query_info.sn', 
            'hsx_phone_query_info.type_id',  
            'hsx_phone_query_info.order_id',
            'hsx_phone_query_info.service_code',
            'hsx_phone_query_info.channel_key',
            'hsx_phone_query_info.query_param',
            'hsx_phone_query_info.info', 
            'hsx_phone_query_info.query_status',
            'hsx_phone_query_info.refund_status',
            'hsx_phone_query_info.refund_money',
            'hsx_phone_query_info.refund_point',
            'hsx_phone_query_info.fail_reason',
            'hsx_phone_query_info.create_time', 
            'hsx_phone_query_info.is_look', 
            'hsx_phone_query_info.member_id',
            'hsx_phone_query_info.pay_type',
            'hsx_phone_query_info.money',
            'member.nickname as member_nickname'  // 直接取会员昵称
            
        ];
        
        $condition = [];
        $condition[] = ['hsx_phone_query_info.site_id', '=', $this->site_id];
        
        // 关键词搜索
        if (!empty($params['keyword'])) {
            $condition[] = ['hsx_phone_query_info.sn', 'like', "%{$params['keyword']}%"];
        }
        
        // 时间范围处理
        if (!empty($params['start_time'])) {
            $start_time = strtotime($params['start_time'] . ' 00:00:00');
            $condition[] = ['hsx_phone_query_info.create_time', '>=', $start_time];
        }
        if (!empty($params['end_time'])) {
            $end_time = strtotime($params['end_time'] . ' 23:59:59');
            $condition[] = ['hsx_phone_query_info.create_time', '<=', $end_time];
        }
        if (!empty($params['service_code'])) {
            $condition[] = ['hsx_phone_query_info.service_code', '=', (string)$params['service_code']];
        }
        if (!empty($params['channel_key'])) {
            $condition[] = ['hsx_phone_query_info.channel_key', '=', (string)$params['channel_key']];
        }

        $model = new HsxPhoneQueryInfo();
        $searchModel = $model->alias('hsx_phone_query_info')  // 添加别名
            ->withJoin(['member'])
            ->where($condition);

        $this->appendOperationalFilters($searchModel, $params);

        $list = $searchModel
            ->field($field)
            ->order('hsx_phone_query_info.create_time desc')
            ->append(['type_name'])
            ->page($params['page'], $params['limit'])
            ->select()
            ->toArray();

        $countModel = $model->alias('hsx_phone_query_info')->where($condition);
        $this->appendOperationalFilters($countModel, $params);
        $count = $countModel->count();

        // 格式化数据
        foreach ($list as &$item) {
            $order = $this->getOrderSnapshot((int)$item['id'], (int)($item['order_id'] ?? 0));
            $apiLog = (new HsxPhoneQueryApiLog())
                ->where([
                    ['site_id', '=', $this->site_id],
                    ['result_id', '=', (int)$item['id']],
                ])
                ->field('id,provider_key,provider_name,channel_key,channel_name,service_code,endpoint_type,endpoint_value,query_param,status,cost_price,duration_ms,response_code,response_message,error_message,create_time')
                ->order('id desc')
                ->findOrEmpty()
                ->toArray();

            $item['order_info'] = $order;
            $item['api_log'] = $this->formatApiLog($apiLog);
            $item['cost_money'] = $order['cost_money'] ?? 0;
            $item['profit_money'] = $order['profit_money'] ?? (float)($item['money'] ?? 0);
            $item['provider_key'] = $apiLog['provider_key'] ?? '';
            $item['provider_name'] = $order['provider_name'] ?? ($apiLog['provider_name'] ?? '');
            $item['channel_key'] = $item['channel_key'] ?: ($order['channel_key'] ?? ($apiLog['channel_key'] ?? ''));
            $item['channel_name'] = $order['channel_name'] ?? ($apiLog['channel_name'] ?? '');
            $item['from_cache_count'] = $order['from_cache_count'] ?? 0;
            $item['notice_status'] = $order['notice_status'] ?? 0;

            if (!empty($item['info'])) {
                $item['info'] = is_array($item['info']) ? $item['info'] : json_decode($item['info'], true);
            }
            if (!empty($item['create_time']) && is_numeric($item['create_time'])) {
                $item['create_time'] = date('Y-m-d H:i:s', intval($item['create_time']));
            }
            $display = $this->resultFormatter->format($item['info'] ?? [], [
                'type_name' => $item['type_name'] ?? '',
                'create_time' => $item['create_time'] ?? '',
            ]);
            $item['display_info'] = $display['fields'];
            $item['display_summary'] = $display['summary'];
            $item['display_status_tags'] = $display['status_tags'];
            
            // 简化会员信息
            $item['member_info'] = [
                'nickname' => $item['member_nickname'] ?? ''
                
            ];
            unset($item['member_nickname']);
        }

        return [
            'count' => $count,
            'list' => $list
        ];
    }

    /**
     * 获取查询记录详情
     */
    public function getInfo($id)
    {
        $field = 'id,sn,type_id,order_id,service_code,channel_key,query_param,info,query_status,refund_status,refund_money,refund_point,fail_reason,create_time,is_look,member_id,pay_type,money';
        
        $info = (new HsxPhoneQueryInfo())->where([['id', '=', $id]])
            ->where('site_id', '=', $this->site_id)
            ->field($field)
            ->append(['type_name'])
            ->find();

        if (!$info) {
            return [];
        }

        $data = $info->toArray();
        
        // 格式化数据
        if (!empty($data['info'])) {
            $data['info'] = is_array($data['info']) ? $data['info'] : json_decode($data['info'], true);
        }
        if (!empty($data['create_time']) && is_numeric($data['create_time'])) {
            $data['create_time'] = date('Y-m-d H:i:s', intval($data['create_time']));
        }
        $display = $this->resultFormatter->format($data['info'] ?? [], [
            'type_name' => $data['type_name'] ?? '',
            'create_time' => $data['create_time'] ?? '',
        ]);
        $data['display_info'] = $display['fields'];
        $data['display_summary'] = $display['summary'];
        $data['display_status_tags'] = $display['status_tags'];

        $data['order_info'] = $this->getOrderSnapshot((int)$data['id'], (int)($data['order_id'] ?? 0), true);
        $data['api_logs'] = (new HsxPhoneQueryApiLog())
            ->where([
                ['site_id', '=', $this->site_id],
                ['result_id', '=', (int)$data['id']],
            ])
            ->field('id,provider_key,provider_name,channel_key,channel_name,service_code,endpoint_type,endpoint_value,query_param,query_code,request_method,request_url,request_params,response_code,response_message,response_data,cost_price,duration_ms,status,error_message,create_time')
            ->order('id desc')
            ->select()
            ->toArray();
        foreach ($data['api_logs'] as &$log) {
            $log = $this->formatApiLog($log, true);
        }

        return $data;
    }

    /**
     * 获取运营看板数据。
     */
    public function getDashboard(array $params = []): array
    {
        [$startTime, $endTime] = $this->resolveTimeRange($params);

        $paidStatuses = [
            HsxPhoneQueryOrderDict::PAID,
            HsxPhoneQueryOrderDict::QUERYING,
            HsxPhoneQueryOrderDict::SUCCESS,
            HsxPhoneQueryOrderDict::FAIL,
        ];

        $orderCount = $this->buildOrderQuery($startTime, $endTime)->where('status', 'in', $paidStatuses)->count();
        $queryCount = (int)$this->buildOrderQuery($startTime, $endTime)->where('status', 'in', $paidStatuses)->sum('query_count');
        $resultCount = (new HsxPhoneQueryInfo())->where([
            ['site_id', '=', $this->site_id],
            ['create_time', '>=', $startTime],
            ['create_time', '<=', $endTime],
        ])->count();
        $incomeMoney = (float)$this->buildOrderQuery($startTime, $endTime)->where('status', 'in', $paidStatuses)->sum('pay_money');
        $successIncomeMoney = (float)$this->buildOrderQuery($startTime, $endTime)->where('status', '=', HsxPhoneQueryOrderDict::SUCCESS)->sum('pay_money');
        $costMoney = (float)$this->buildApiLogQuery($startTime, $endTime)->where('status', '=', 'success')->sum('cost_price');
        $cacheCount = (int)$this->buildOrderQuery($startTime, $endTime)->where('status', 'in', $paidStatuses)->sum('from_cache_count');
        $orderFailCount = $this->buildOrderQuery($startTime, $endTime)->where('status', '=', HsxPhoneQueryOrderDict::FAIL)->count();
        $apiCallCount = $this->buildApiLogQuery($startTime, $endTime)->count();
        $apiFailCount = $this->buildApiLogQuery($startTime, $endTime)->where('status', '=', 'fail')->count();

        $providerStats = $this->buildApiLogQuery($startTime, $endTime)
            ->field('channel_key,channel_name,provider_key,provider_name,count(*) as query_count,sum(cost_price) as cost_money,sum(if(status = "fail", 1, 0)) as fail_count,round(avg(duration_ms),0) as avg_duration_ms')
            ->group('channel_key,channel_name,provider_key,provider_name')
            ->order('cost_money desc,query_count desc')
            ->select()
            ->toArray();

        $serviceStats = $this->buildApiLogQuery($startTime, $endTime)
            ->field('service_code,service_name,count(*) as query_count,sum(cost_price) as cost_money,sum(if(status = "fail", 1, 0)) as fail_count,round(avg(duration_ms),0) as avg_duration_ms')
            ->group('service_code,service_name')
            ->order('cost_money desc,query_count desc')
            ->limit(20)
            ->select()
            ->toArray();

        return [
            'range' => [
                'start_time' => date('Y-m-d H:i:s', $startTime),
                'end_time' => date('Y-m-d H:i:s', $endTime),
            ],
            'overview' => [
                'order_count' => (int)$orderCount,
                'query_count' => (int)$queryCount,
                'result_count' => (int)$resultCount,
                'api_call_count' => (int)$apiCallCount,
                'cache_count' => (int)$cacheCount,
                'income_money' => round($incomeMoney, 3),
                'success_income_money' => round($successIncomeMoney, 3),
                'cost_money' => round($costMoney, 3),
                'profit_money' => round($incomeMoney - $costMoney, 3),
                'order_fail_count' => (int)$orderFailCount,
                'api_fail_count' => (int)$apiFailCount,
                'api_fail_rate' => $apiCallCount > 0 ? round($apiFailCount / $apiCallCount * 100, 2) : 0,
            ],
            'provider_stats' => $this->formatStatRows($providerStats),
            'service_stats' => $this->formatStatRows($serviceStats),
        ];
    }

    private function appendOperationalFilters($query, array $params): void
    {
        $orderTable = (new HsxPhoneQueryOrder())->getTable();

        if (!empty($params['provider_key'])) {
            $logTable = (new HsxPhoneQueryApiLog())->getTable();
            $query->whereRaw("EXISTS (SELECT 1 FROM `{$logTable}` api_log WHERE api_log.result_id = hsx_phone_query_info.id AND api_log.site_id = ? AND api_log.provider_key = ?)", [$this->site_id, (string)$params['provider_key']]);
        }
        if (array_key_exists('status', $params) && $params['status'] !== '' && $params['status'] !== null) {
            $query->whereRaw("EXISTS (SELECT 1 FROM `{$orderTable}` query_order WHERE query_order.site_id = ? AND query_order.status = ? AND (query_order.order_id = hsx_phone_query_info.order_id OR FIND_IN_SET(hsx_phone_query_info.id, query_order.result_ids)))", [$this->site_id, (int)$params['status']]);
        }
    }

    private function getOrderSnapshot(int $resultId, int $orderId, bool $withFailReason = false): array
    {
        $field = 'order_id,order_no,status,pay_type,pay_money,pay_point,cost_money,profit_money,provider_name,channel_key,channel_name,query_param,endpoint_type,endpoint_value,from_cache_count,notice_status';
        if ($withFailReason) {
            $field .= ',fail_reason';
        }

        $query = (new HsxPhoneQueryOrder())->where('site_id', '=', $this->site_id);
        if ($orderId > 0) {
            $query->where('order_id', '=', $orderId);
        } else {
            $query->whereRaw('FIND_IN_SET(' . $resultId . ', result_ids)');
        }

        return $query->field($field)->findOrEmpty()->toArray();
    }

    private function resolveTimeRange(array $params): array
    {
        $start = !empty($params['start_time']) ? strtotime($params['start_time'] . ' 00:00:00') : strtotime(date('Y-m-d') . ' 00:00:00');
        $end = !empty($params['end_time']) ? strtotime($params['end_time'] . ' 23:59:59') : strtotime(date('Y-m-d') . ' 23:59:59');

        return [$start, $end];
    }

    private function buildOrderQuery(int $startTime, int $endTime)
    {
        return (new HsxPhoneQueryOrder())->where([
            ['site_id', '=', $this->site_id],
            ['create_time', '>=', $startTime],
            ['create_time', '<=', $endTime],
        ]);
    }

    private function buildApiLogQuery(int $startTime, int $endTime)
    {
        return (new HsxPhoneQueryApiLog())->where([
            ['site_id', '=', $this->site_id],
            ['create_time', '>=', $startTime],
            ['create_time', '<=', $endTime],
        ]);
    }

    private function formatApiLog(array $log, bool $withPayload = false): array
    {
        if (empty($log)) {
            return [];
        }

        if (!empty($log['create_time']) && is_numeric($log['create_time'])) {
            $log['create_time'] = date('Y-m-d H:i:s', (int)$log['create_time']);
        }
        if ($withPayload) {
            foreach (['request_params', 'response_data'] as $field) {
                if (!empty($log[$field]) && is_string($log[$field])) {
                    $decoded = json_decode($log[$field], true);
                    $log[$field] = is_array($decoded) ? $decoded : $log[$field];
                }
            }
        } else {
            unset($log['request_params'], $log['response_data']);
        }

        return $log;
    }

    private function formatStatRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['query_count'] = (int)($row['query_count'] ?? 0);
            $row['fail_count'] = (int)($row['fail_count'] ?? 0);
            $row['cost_money'] = round((float)($row['cost_money'] ?? 0), 3);
            $row['avg_duration_ms'] = (int)($row['avg_duration_ms'] ?? 0);
            $row['fail_rate'] = $row['query_count'] > 0 ? round($row['fail_count'] / $row['query_count'] * 100, 2) : 0;
        }

        return $rows;
    }
}
