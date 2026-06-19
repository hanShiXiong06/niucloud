<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\core\ai;

use addon\hsx_erp\app\service\admin\DeviceTraceService;
use addon\hsx_erp\app\service\admin\ErpAssetService;
use addon\hsx_erp\app\service\admin\ErpCounterpartyAdminService;
use addon\hsx_erp\app\service\admin\FinanceCounterpartyBalanceService;
use app\service\admin\auth\AuthService;
use think\facade\Log;

/**
 * AI 可调用的「只读查询工具」注册表（Function Calling）
 *
 * 安全要点：
 *  - 每个工具声明它对应的业务接口权限（api_url + method）。执行前用 AuthService::getAuthApiList()
 *    校验当前操作人是否拥有这些接口权限——与走 route 时 AdminCheckRole 的判定完全一致。
 *    这样既保留了 route 的权限管控，又省掉 HTTP/loopback 开销。
 *  - 工具内部走 admin Service，Service 以 $this->site_id 锁定本站点，不可跨站、不可写库、不可任意 SQL。
 */
class AiToolService
{
    /** 当前操作人允许的接口表 [method => [api_url,...]]，懒加载 */
    protected ?array $authApi = null;

    protected function registry(): array
    {
        return [
            'search_counterparty' => [
                'definition' => [
                    'type'     => 'function',
                    'function' => [
                        'name'        => 'search_counterparty',
                        'description' => '按关键词（姓名、昵称或手机号）搜索对接人，返回匹配到的人，以及每个人的 member_id 和所属主体 entity_id / entity_name。要查某人/某手机号的往来时，先用它，再拿 entity_id 去 get_counterparty_dealings。',
                        'parameters'  => [
                            'type'       => 'object',
                            'properties' => [
                                'keyword' => ['type' => 'string', 'description' => '姓名 / 昵称 / 手机号，任意片段即可'],
                            ],
                            'required'   => ['keyword'],
                        ],
                    ],
                ],
                'permissions' => [['api' => 'erp/counterparty/lists', 'method' => 'get']],
                'handler'     => fn(array $args) => $this->searchCounterparty($args),
            ],

            'get_counterparty_dealings' => [
                'definition' => [
                    'type'     => 'function',
                    'function' => [
                        'name'        => 'get_counterparty_dealings',
                        'description' => '查询某主体的往来流水明细（含应收应付、含已结清）。优先传 entity_id（主体），会自动展开主体下全部对接人并汇总，每笔标注归属是谁（owner），并带 device_id 可进一步查货。默认只查最近 7 天；要查全部历史时把 all 设为 true，或显式传 start_date/end_date。',
                        'parameters'  => [
                            'type'       => 'object',
                            'properties' => [
                                'entity_id'  => ['type' => 'integer', 'description' => '主体(往来单位)id，优先用它'],
                                'member_id'  => ['type' => 'integer', 'description' => '对接人 id，仅当无主体时用'],
                                'start_date' => ['type' => 'string', 'description' => '起始日期 YYYY-MM-DD，可选'],
                                'end_date'   => ['type' => 'string', 'description' => '结束日期 YYYY-MM-DD，可选'],
                                'all'        => ['type' => 'boolean', 'description' => '查全部历史(不限时间)时传 true'],
                            ],
                        ],
                    ],
                ],
                'permissions' => [
                    ['api' => 'erp/finance/payable/lists', 'method' => 'get'],
                    ['api' => 'erp/finance/receivable/lists', 'method' => 'get'],
                ],
                'handler' => fn(array $args) => $this->getCounterpartyDealings($args),
            ],

            'get_finance_summary' => [
                'definition' => [
                    'type'     => 'function',
                    'function' => [
                        'name'        => 'get_finance_summary',
                        'description' => '查询全站财务汇总：应收合计、应付合计、净额、各资金账户余额。用于整体经营/资金概览。',
                        'parameters'  => ['type' => 'object', 'properties' => new \stdClass()],
                    ],
                ],
                'permissions' => [['api' => 'erp/finance/board', 'method' => 'get']],
                'handler'     => fn(array $args) => $this->getFinanceSummary(),
            ],

            'get_device_detail' => [
                'definition' => [
                    'type'     => 'function',
                    'function' => [
                        'name'        => 'get_device_detail',
                        'description' => '按设备 device_id 查这台货(设备)的全链路明细：型号/IMEI、从谁回收、卖给谁、回收价/成本/售价/利润、收款情况，以及完整时间线(每一步的阶段、动作、金额和经手人/操作人)。往来流水里每笔的 device_id 可传进来，回答「这笔账对应什么货、谁操作的」时用它。',
                        'parameters'  => [
                            'type'       => 'object',
                            'properties' => [
                                'device_id' => ['type' => 'integer', 'description' => '设备 id（往来流水记录里的 device_id）'],
                            ],
                            'required'   => ['device_id'],
                        ],
                    ],
                ],
                'permissions' => [['api' => 'erp/device_trace/detail', 'method' => 'get']],
                'handler'     => fn(array $args) => $this->getDeviceDetail($args),
            ],

            'get_business_report' => [
                'definition' => [
                    'type'     => 'function',
                    'function' => [
                        'name'        => 'get_business_report',
                        'description' => '查询一段时间内的经营情况：采购入库(笔数/金额)、销售(笔数/销售额/成本/毛利润)、期末在库(数量/成本)。问到「经营情况/业绩/利润/毛利/采购销售」时用它。默认查最近 7 天；可传 period(today/this_week/this_month/last_month/this_year) 或显式 start_date/end_date。',
                        'parameters'  => [
                            'type'       => 'object',
                            'properties' => [
                                'period'     => ['type' => 'string', 'description' => 'today/this_week/this_month/last_month/this_year，可选'],
                                'start_date' => ['type' => 'string', 'description' => '起始日期 YYYY-MM-DD，可选'],
                                'end_date'   => ['type' => 'string', 'description' => '结束日期 YYYY-MM-DD，可选'],
                            ],
                        ],
                    ],
                ],
                'permissions' => [['api' => 'erp/asset/lists', 'method' => 'get']],
                'handler'     => fn(array $args) => $this->getBusinessReport($args),
            ],

            'list_inventory' => [
                'definition' => [
                    'type'     => 'function',
                    'function' => [
                        'name'        => 'list_inventory',
                        'description' => '列出库存设备清单(每台的型号/IMEI/成本/售价/状态/仓库, 带 device_id 可进一步查全链路)。当用户问「在库/库存有哪些机器、列出库存、这几台分别是哪些、可售有哪些」时用它——不要用 get_counterparty_dealings(那是查某人往来账的)。'
                            . 'status 可选: onhand(在手未售, 默认)/available(可售)/in_stock(在库)/refurbishing(整备中)/locked(已售锁定:挂单卖给同行待结)/sold(已售)/all(全部); keyword 可按型号或IMEI过滤。',
                        'parameters'  => [
                            'type'       => 'object',
                            'properties' => [
                                'status'  => ['type' => 'string', 'description' => 'onhand/available/in_stock/refurbishing/locked/sold/all，默认 onhand'],
                                'keyword' => ['type' => 'string', 'description' => '型号或 IMEI 片段，可选'],
                            ],
                        ],
                    ],
                ],
                'permissions' => [['api' => 'erp/asset/lists', 'method' => 'get']],
                'handler'     => fn(array $args) => (new ErpAssetService())->listForAi((string)($args['status'] ?? 'onhand'), (string)($args['keyword'] ?? ''), 50),
            ],
        ];
    }

    public function definitions(array $allow): array
    {
        $defs = [];
        foreach ($this->registry() as $name => $tool) {
            if (in_array($name, $allow, true)) {
                $defs[] = $tool['definition'];
            }
        }
        return $defs;
    }

    public function execute(string $name, array $args, array $allow): array
    {
        if (!in_array($name, $allow, true)) {
            return ['error' => '工具未授权: ' . $name];
        }
        $tool = $this->registry()[$name] ?? null;
        if (!$tool) {
            return ['error' => '未知工具: ' . $name];
        }
        // 权限校验：与走 route 时 AdminCheckRole 一致
        $denied = $this->permissionDenied($tool['permissions'] ?? []);
        if ($denied !== null) {
            return ['error' => '无权限：你没有访问「' . $denied . '」所需的接口权限，无法查询该数据'];
        }
        try {
            return $tool['handler']($args);
        } catch (\Throwable $e) {
            Log::write('[hsx_ai] tool ' . $name . ' error: ' . $e->getMessage(), 'error');
            return ['error' => '工具执行失败: ' . $e->getMessage()];
        }
    }

    /**
     * 校验工具所需接口权限；缺哪个返回哪个 api_url，全部通过返回 null
     */
    protected function permissionDenied(array $permissions): ?string
    {
        if (empty($permissions)) {
            return null;
        }
        if ($this->authApi === null) {
            try {
                $this->authApi = (new AuthService())->getAuthApiList() ?: [];
            } catch (\Throwable $e) {
                $this->authApi = [];
            }
        }
        foreach ($permissions as $p) {
            $method = strtolower((string)($p['method'] ?? 'get'));
            $api    = strtolower((string)($p['api'] ?? ''));
            $list   = array_map('strtolower', $this->authApi[$method] ?? []);
            if ($api !== '' && !in_array($api, $list, true)) {
                return $api;
            }
        }
        return null;
    }

    /**
     * 调用前的进度文案（流式状态用）
     */
    public function statusBefore(string $name, array $args): string
    {
        switch ($name) {
            case 'search_counterparty':
                return '正在查找「' . (string)($args['keyword'] ?? '') . '」相关的人…';
            case 'get_counterparty_dealings':
                return '正在查询往来账目明细…';
            case 'get_finance_summary':
                return '正在汇总全站财务数据…';
            case 'get_device_detail':
                return '正在查询设备(货)明细与经手人…';
            case 'get_business_report':
                return '正在统计经营数据(采购/销售/毛利)…';
            case 'list_inventory':
                return '正在列出库存设备清单…';
            default:
                return '正在查询…';
        }
    }

    /**
     * 调用后的进度文案
     */
    public function statusAfter(string $name, array $result): string
    {
        if (isset($result['error'])) {
            return '查询受限：' . (string)$result['error'];
        }
        switch ($name) {
            case 'search_counterparty':
                $n = (int)($result['count'] ?? 0);
                if ($n === 0) {
                    return '没有找到匹配的人';
                }
                $names = array_slice(array_map(fn($x) => (string)($x['name'] ?? ''), $result['list'] ?? []), 0, 3);
                return '找到 ' . $n . ' 个相关的人：' . implode('、', array_filter($names));
            case 'get_counterparty_dealings':
                $entity = (string)($result['entity_name'] ?? '');
                $cnt = (int)($result['record_count'] ?? 0);
                return ($entity !== '' ? '主体「' . $entity . '」' : '') . '共 ' . $cnt . ' 笔往来';
            case 'get_finance_summary':
                return '财务汇总已取得';
            case 'get_device_detail':
                $g = $result['goods'] ?? [];
                $model = (string)($g['model'] ?? '');
                return $model !== '' ? ('设备：' . $model . '，已取得货品与经手记录') : '已取得设备明细';
            case 'get_business_report':
                $s = $result['sales'] ?? [];
                return '经营数据已取得：销售 ' . (int)($s['count'] ?? 0) . ' 台，毛利 ' . (float)($s['gross_profit'] ?? 0) . ' 元';
            case 'list_inventory':
                return '已取得库存清单：共 ' . (int)($result['total'] ?? ($result['count'] ?? 0)) . ' 台';
            default:
                return '查询完成';
        }
    }

    // —— 工具实现（只读，site_id 由 Service 内部锁定） ——

    protected function searchCounterparty(array $args): array
    {
        $keyword = trim((string)($args['keyword'] ?? ''));
        if ($keyword === '') {
            return ['error' => 'keyword 不能为空'];
        }
        $members = (new ErpCounterpartyAdminService())->memberOptions($keyword);
        $list = [];
        foreach ($members as $m) {
            $list[] = [
                'member_id'   => (int)($m['member_id'] ?? 0),
                'name'        => (string)($m['nickname'] ?? '') ?: (string)($m['username'] ?? ''),
                'mobile'      => (string)($m['mobile'] ?? ''),
                'entity_id'   => (int)($m['counterparty_id'] ?? 0),
                'entity_name' => (string)($m['counterparty_name'] ?? ''),
            ];
        }
        return ['count' => count($list), 'list' => $list];
    }

    protected function getCounterpartyDealings(array $args): array
    {
        $entityId = (int)($args['entity_id'] ?? 0);
        $memberId = (int)($args['member_id'] ?? 0);
        if ($entityId <= 0 && $memberId <= 0) {
            return ['error' => '需要 entity_id 或 member_id'];
        }
        // 时间窗：默认最近 7 天；all=true 或显式日期可覆盖
        $start = 0;
        $end = 0;
        $sd = trim((string)($args['start_date'] ?? ''));
        $ed = trim((string)($args['end_date'] ?? ''));
        $all = !empty($args['all']);
        if (!$all) {
            if ($sd !== '' || $ed !== '') {
                $start = $sd !== '' ? (int)strtotime($sd . ' 00:00:00') : 0;
                $end   = $ed !== '' ? (int)strtotime($ed . ' 23:59:59') : time();
            } else {
                $start = strtotime('-7 days', strtotime('today'));
                $end   = time();
            }
        }
        $res = (new ErpCounterpartyAdminService())->counterpartyDealings($entityId, $memberId, $start, $end);
        // 防爆：明细很多时只把前 N 笔喂给 AI（totals 仍是全量统计，不影响汇总判断）
        $cap = 200;
        if (is_array($res['records'] ?? null) && count($res['records']) > $cap) {
            $total = count($res['records']);
            $res['records'] = array_slice($res['records'], 0, $cap);
            $res['records_truncated'] = true;
            $res['note'] = trim((string)($res['note'] ?? '') . " 明细较多，仅返回前 {$cap} 笔（共 {$total} 笔），合计数为全部数据；如需更精确请缩小时间范围或指定对接人。");
        }
        return $res;
    }

    protected function getBusinessReport(array $args): array
    {
        [$start, $end] = $this->resolveRange($args);
        return (new ErpAssetService())->businessReport($start, $end);
    }

    /**
     * 解析时间区间：显式 start/end > period > 默认最近 7 天
     * @return array{0:int,1:int}
     */
    private function resolveRange(array $args): array
    {
        $sd = trim((string)($args['start_date'] ?? ''));
        $ed = trim((string)($args['end_date'] ?? ''));
        if ($sd !== '' && $ed !== '') {
            return [(int)strtotime($sd . ' 00:00:00'), (int)strtotime($ed . ' 23:59:59')];
        }
        $now = time();
        switch ((string)($args['period'] ?? '')) {
            case 'today':
                return [strtotime('today'), $now];
            case 'this_week':
                return [strtotime('monday this week'), $now];
            case 'this_month':
                return [strtotime(date('Y-m') . '-01 00:00:00'), $now];
            case 'last_month':
                return [strtotime('first day of last month 00:00:00'), strtotime('first day of this month 00:00:00') - 1];
            case 'this_year':
                return [strtotime(date('Y') . '-01-01 00:00:00'), $now];
            default:
                // 默认最近 7 天
                return [strtotime('-7 days', strtotime('today')), $now];
        }
    }

    protected function getFinanceSummary(): array
    {
        return (new FinanceCounterpartyBalanceService())->getSummary();
    }

    protected function getDeviceDetail(array $args): array
    {
        $deviceId = (int)($args['device_id'] ?? 0);
        if ($deviceId <= 0) {
            return ['error' => 'device_id 无效'];
        }
        $d = (new DeviceTraceService())->detail(0, $deviceId);
        $ov = $d['overview'] ?? [];

        $timeline = [];
        foreach (($d['events'] ?? []) as $e) {
            $timeline[] = [
                'time'     => !empty($e['time']) ? date('Y-m-d H:i', (int)$e['time']) : '',
                'stage'    => (string)($e['stage'] ?? ''),
                'action'   => (string)($e['title'] ?? ''),
                'detail'   => (string)($e['detail'] ?? ''),
                'operator' => (string)($e['operator_name'] ?? ''),   // 经手人/操作人
                'amount'   => round((float)($e['amount'] ?? 0), 2),
                'no'       => (string)($e['no'] ?? ''),
            ];
        }

        return [
            'device_id' => $deviceId,
            'goods'     => [
                'model'            => (string)($ov['model'] ?? ''),
                'imei'             => (string)($ov['imei'] ?? ''),
                'imei2'            => (string)($ov['imei2'] ?? ''),
                'sn'               => (string)($ov['sn'] ?? ''),
                'capacity'         => (string)($ov['capacity'] ?? ''),   // 内存/容量
                'color'            => (string)($ov['color'] ?? ''),
                'check'            => $ov['check'] ?? [],                // 质检快照(结构不固定)
                'asset_no'         => (string)($ov['asset_no'] ?? ''),
                'order_no'         => (string)($ov['order_no'] ?? ''),
                'inventory_status' => (string)($ov['inventory_status'] ?? ''),
                'recycle_from'     => (string)($ov['customer_name'] ?? ''),   // 从谁回收
                'sell_to'          => (string)($ov['buyer_name'] ?? ''),      // 卖给谁
                'recycle_price'    => $ov['recycle_price'] ?? 0,
                'current_cost'     => $ov['current_cost'] ?? 0,
                'sale_price'       => $ov['sale_price'] ?? 0,
                'profit'           => $ov['profit'] ?? 0,
                'received'         => $ov['received'] ?? 0,
                'unreceived'       => $ov['unreceived'] ?? 0,
            ],
            'timeline'  => $timeline,
        ];
    }
}
