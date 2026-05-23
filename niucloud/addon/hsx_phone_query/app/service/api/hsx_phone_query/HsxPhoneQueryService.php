<?php

namespace addon\hsx_phone_query\app\service\api\hsx_phone_query;

use addon\hsx_phone_query\app\dict\HsxPhoneQueryOrderDict;
use addon\hsx_phone_query\app\dict\HsxPhoneQueryConfigDict;
use addon\hsx_phone_query\app\model\HsxPhoneQueryApiLog;
use addon\hsx_phone_query\app\model\HsxPhoneQueryCategory;
use addon\hsx_phone_query\app\model\HsxPhoneQueryInfo;
use addon\hsx_phone_query\app\model\HsxPhoneQueryOrder;
use addon\hsx_phone_query\app\service\core\provider\ProviderChannelService;
use addon\hsx_phone_query\app\service\core\report\QueryResultFormatter;
use app\dict\member\MemberAccountTypeDict;
use app\dict\pay\RefundDict;
use app\model\member\Member;
use app\service\core\member\CoreMemberAccountService;
use app\service\core\notice\NoticeService;
use app\service\core\pay\CorePayService;
use app\service\core\pay\CoreRefundService;
use core\base\BaseApiService;
use core\exception\CommonException;
use think\facade\Log;

/**
 * 手机查询服务层
 */
class HsxPhoneQueryService extends BaseApiService
{
    private const API_URL = 'https://api-srv.gkdt.com/inquiry/async';
    private const API_STYLE = '11';
    private const CACHE_TTL = 86400;

    private HsxPhoneQueryCategory $categoryModel;
    private HsxPhoneQueryInfo $queryInfoModel;
    private HsxPhoneQueryOrder $orderModel;
    private Member $memberModel;
    private CoreMemberAccountService $accountService;
    private ProviderChannelService $providerChannelService;
    private QueryResultFormatter $resultFormatter;

    public function __construct()
    {
        parent::__construct();
        $this->categoryModel = new HsxPhoneQueryCategory();
        $this->queryInfoModel = new HsxPhoneQueryInfo();
        $this->orderModel = new HsxPhoneQueryOrder();
        $this->memberModel = new Member();
        $this->accountService = new CoreMemberAccountService();
        $this->providerChannelService = new ProviderChannelService();
        $this->resultFormatter = new QueryResultFormatter();
    }

    /**
     * 获取水印配置
     */
    public function getWatermark()
    {
        return $this->getDisplayConfig()['watermark'];
    }

    public function getDisplayConfig(): array
    {
        $config = $this->getProviderConfig();
        return HsxPhoneQueryConfigDict::normalizeDisplayConfig($config['display_config'] ?? []);
    }

    /**
     * 兼容旧接口：积分查询直接执行；现金查询只创建待支付订单。
     */
    public function query(array $params = [])
    {
        if (($params['payType'] ?? '') == HsxPhoneQueryOrderDict::PAY_TYPE_POINT) {
            return ['code' => 200, 'data' => $this->pointQuery($params)];
        }

        return ['code' => 200, 'data' => $this->createMoneyOrder($params)];
    }

    /**
     * 创建现金支付查询订单，后续由框架支付组件拉起支付。
     */
    public function createMoneyOrder(array $params = []): array
    {
        $this->validateQueryParams($params);
        $imeis = $this->parseImeiList($params['imeis']);
        $queryConfig = $this->getQueryConfig((int)$params['id']);
        $queryCount = count($imeis);
        $payMoney = round((float)$queryConfig['price'] * $queryCount, 3);
        $orderSnapshot = $this->buildOrderSnapshot($queryConfig);

        $orderId = $this->orderModel->insertGetId([
            'order_no' => $this->createOrderNo(),
            'site_id' => $this->site_id,
            'member_id' => $this->member_id,
            'imeis' => implode(',', $imeis),
            'type_id' => (int)$params['id'],
            'pid' => (int)($params['pid'] ?? 0),
            'service_code' => $queryConfig['service_code'],
            'service_name' => $queryConfig['name'],
            'channel_key' => $orderSnapshot['channel_key'],
            'channel_name' => $orderSnapshot['channel_name'],
            'query_param' => $orderSnapshot['query_param'],
            'endpoint_type' => $orderSnapshot['endpoint_type'],
            'endpoint_value' => $orderSnapshot['endpoint_value'],
            'pay_type' => HsxPhoneQueryOrderDict::PAY_TYPE_MONEY,
            'unit_price' => (float)$queryConfig['price'],
            'unit_cost' => (float)$queryConfig['cost_price'],
            'pay_money' => $payMoney,
            'pay_point' => 0,
            'query_count' => $queryCount,
            'success_count' => 0,
            'fail_count' => 0,
            'cost_money' => 0,
            'profit_money' => $payMoney,
            'provider_name' => $orderSnapshot['provider_name'],
            'from_cache_count' => 0,
            'status' => HsxPhoneQueryOrderDict::WAIT_PAY,
            'create_time' => time(),
            'update_time' => time(),
        ]);

        return [
            'order_id' => $orderId,
            'order_no' => $this->orderModel->where('order_id', $orderId)->value('order_no'),
            'pay_money' => $payMoney,
            'query_count' => $queryCount,
            'trade_type' => HsxPhoneQueryOrderDict::TRADE_TYPE,
        ];
    }

    /**
     * 积分查询：后端判断积分、扣积分并立即执行查询。
     */
    public function pointQuery(array $params = []): array
    {
        $this->validateQueryParams($params);
        $imeis = $this->parseImeiList($params['imeis']);
        $queryConfig = $this->getQueryConfig((int)$params['id']);
        $queryCount = count($imeis);
        $payMoney = round((float)$queryConfig['price'] * $queryCount, 3);
        $payPoint = (int)round($payMoney * 100);
        $orderSnapshot = $this->buildOrderSnapshot($queryConfig);

        $this->deductPoint($payPoint, (int)$params['id']);

        $orderId = $this->orderModel->insertGetId([
            'order_no' => $this->createOrderNo(),
            'site_id' => $this->site_id,
            'member_id' => $this->member_id,
            'imeis' => implode(',', $imeis),
            'type_id' => (int)$params['id'],
            'pid' => (int)($params['pid'] ?? 0),
            'service_code' => $queryConfig['service_code'],
            'service_name' => $queryConfig['name'],
            'channel_key' => $orderSnapshot['channel_key'],
            'channel_name' => $orderSnapshot['channel_name'],
            'query_param' => $orderSnapshot['query_param'],
            'endpoint_type' => $orderSnapshot['endpoint_type'],
            'endpoint_value' => $orderSnapshot['endpoint_value'],
            'pay_type' => HsxPhoneQueryOrderDict::PAY_TYPE_POINT,
            'unit_price' => (float)$queryConfig['price'],
            'unit_cost' => (float)$queryConfig['cost_price'],
            'pay_money' => $payMoney,
            'pay_point' => $payPoint,
            'query_count' => $queryCount,
            'success_count' => 0,
            'fail_count' => 0,
            'cost_money' => 0,
            'profit_money' => $payMoney,
            'provider_name' => $orderSnapshot['provider_name'],
            'from_cache_count' => 0,
            'status' => HsxPhoneQueryOrderDict::PAID,
            'pay_time' => time(),
            'create_time' => time(),
            'update_time' => time(),
        ]);

        return $this->executeOrderQuery($orderId);
    }

    /**
     * 支付成功回调入口。
     */
    public function paySuccess(array $payInfo): bool
    {
        if (($payInfo['trade_type'] ?? '') != HsxPhoneQueryOrderDict::TRADE_TYPE) {
            return true;
        }

        $orderId = (int)($payInfo['trade_id'] ?? 0);
        if ($orderId <= 0) {
            return true;
        }

        $order = $this->orderModel->where([
            ['order_id', '=', $orderId],
            ['site_id', '=', (int)$payInfo['site_id']],
        ])->findOrEmpty();

        if ($order->isEmpty()) {
            return true;
        }

        $currentStatus = (int)$order['status'];
        if (in_array($currentStatus, [HsxPhoneQueryOrderDict::SUCCESS, HsxPhoneQueryOrderDict::QUERYING], true)) {
            return true;
        }
        if (!in_array($currentStatus, [HsxPhoneQueryOrderDict::WAIT_PAY, HsxPhoneQueryOrderDict::PAID], true)) {
            return true;
        }

        if ($currentStatus == HsxPhoneQueryOrderDict::WAIT_PAY) {
            $order->save([
                'status' => HsxPhoneQueryOrderDict::PAID,
                'pay_time' => time(),
                'update_time' => time(),
            ]);
        }

        try {
            $result = $this->executeOrderQuery($orderId);
            if ((int)($result['status'] ?? 0) === HsxPhoneQueryOrderDict::FAIL) {
                $this->refundMoneyIfNeeded($order->toArray(), (string)($payInfo['out_trade_no'] ?? ''), (string)($result['fail_reason'] ?? '查询失败'));
            }
        } catch (\Throwable $e) {
            $order->save([
                'status' => HsxPhoneQueryOrderDict::FAIL,
                'fail_reason' => $e->getMessage(),
                'finish_time' => time(),
                'update_time' => time(),
            ]);
            $this->refundMoneyIfNeeded($order->toArray(), (string)($payInfo['out_trade_no'] ?? ''), $e->getMessage());
            $this->sendQueryNotice($orderId, HsxPhoneQueryOrderDict::FAIL, $e->getMessage());
            Log::error('手机查询支付后执行失败：order_id=' . $orderId . ' ' . $e->getMessage());
        }
        return true;
    }

    /**
     * 支付组件创建支付单据时读取订单信息。
     */
    public function getPayOrderInfo(int $siteId, int $orderId): array
    {
        $order = $this->orderModel->where([
            ['site_id', '=', $siteId],
            ['order_id', '=', $orderId],
        ])->findOrEmpty()->toArray();

        if (empty($order)) {
            throw new CommonException('查询订单不存在');
        }
        if ((int)$order['status'] != HsxPhoneQueryOrderDict::WAIT_PAY) {
            throw new CommonException('只有待支付订单可以支付');
        }

        return $order;
    }

    /**
     * 获取用户已查询记录列表
     */
    public function getPage(array $params = [])
    {
        try {
            $query = $this->buildUserOrderListModel($params);
            $result = $this->pageQuery($query);

            if (!empty($result['data'])) {
                $result['data'] = $this->formatOrderResults($result['data']);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('获取查询记录列表失败：' . $e->getMessage());
            throw new CommonException('获取记录失败');
        }
    }

    /**
     * 获取查询记录详情
     */
    public function getInfo(array $params = [])
    {
        $field = 'id, sn, type_id, info, create_time, is_look';

        $data = $this->queryInfoModel
            ->where([
                ['site_id', '=', $this->site_id],
                ['member_id', '=', $this->member_id],
            ])
            ->where($params)
            ->field($field)
            ->append(['type_name'])
            ->find();

        if (empty($data)) {
            throw new CommonException('记录不存在');
        }

        $data = $data->toArray();
        $data['info'] = $this->formatJsonField($data['info']);
        $display = $this->resultFormatter->format($data['info'], [
            'type_name' => $data['type_name'] ?? '',
            'create_time' => $this->formatTimestamp($data['create_time'] ?? 0),
        ]);
        $data['display_info'] = $display['fields'];
        $data['display_summary'] = $display['summary'];
        $data['display_status_tags'] = $display['status_tags'];
        $data['display_title'] = $display['title'];
        $data['display_subtitle'] = $display['subtitle'];
        $data['display_image'] = $display['image'];
        $this->updateViewStatus((int)$data['id']);

        return $data;
    }

    /**
     * 执行订单内所有串号查询。
     */
    private function executeOrderQuery(int $orderId): array
    {
        $order = $this->orderModel->where('order_id', $orderId)->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('查询订单不存在');
        }

        if ((int)$order['status'] == HsxPhoneQueryOrderDict::SUCCESS) {
            return [
                'order_id' => $orderId,
                'results' => [],
                'status' => HsxPhoneQueryOrderDict::SUCCESS,
            ];
        }

        $order->save([
            'status' => HsxPhoneQueryOrderDict::QUERYING,
            'update_time' => time(),
        ]);

        $imeis = $this->parseImeiList($order['imeis']);
        $queryConfig = $this->getQueryConfig((int)$order['type_id'], $order->toArray());
        $results = [];
        $errors = [];
        $resultIds = [];
        $cacheCount = 0;
        $costMoney = 0.0;
        $providers = [];
        $channels = [];

        foreach ($imeis as $imei) {
            try {
                $apiResponse = [];
                $cached = $this->getCachedQueryResult($imei, $order->toArray());
                if (!empty($cached)) {
                    $infoJson = $cached['info'];
                    $data = $this->formatJsonField($infoJson);
                    $cacheCount++;
                } else {
                    $apiResponse = $this->callDeviceQuery($queryConfig, $imei, (int)$order['type_id'], $order->toArray());
                    $data = $apiResponse['data'] ?? [];
                    $infoJson = json_encode($data, JSON_UNESCAPED_UNICODE);
                    $costMoney += (float)($apiResponse['third_cost'] ?? $queryConfig['cost_price'] ?? 0);
                    if (!empty($apiResponse['provider'])) {
                        $providers[] = (string)$apiResponse['provider'];
                    }
                    if (!empty($apiResponse['channel_name'])) {
                        $channels[] = (string)$apiResponse['channel_name'];
                    }
                }

                $resultId = $this->insertQueryInfo($imei, $order->toArray(), $infoJson);
                $resultIds[] = $resultId;
                if (!empty($apiResponse['api_log_id'])) {
                    $this->bindApiLogResult((int)$apiResponse['api_log_id'], $resultId, (int)$order['site_id']);
                }
                $results[] = $data;
            } catch (\Throwable $e) {
                $errors[] = $imei . '：' . $e->getMessage();
                Log::error('手机串号查询失败：' . $imei . ' ' . $e->getMessage());
            }
        }

        $status = empty($results) ? HsxPhoneQueryOrderDict::FAIL : HsxPhoneQueryOrderDict::SUCCESS;
        $failReason = implode("\n", $errors);
        $profitMoney = round((float)$order['pay_money'] - $costMoney, 3);
        $refundStatus = 0;
        $refundTime = 0;
        if ($status == HsxPhoneQueryOrderDict::FAIL) {
            if ($this->refundPointIfNeeded($order->toArray(), $failReason ?: '查询失败')) {
                $refundStatus = 1;
                $refundTime = time();
            }
        }

        $order->save([
            'status' => $status,
            'success_count' => count($results),
            'fail_count' => count($errors),
            'cost_money' => round($costMoney, 3),
            'profit_money' => $profitMoney,
            'provider_name' => !empty($providers) ? implode(',', array_values(array_unique($providers))) : (string)($order['provider_name'] ?? ''),
            'channel_name' => !empty($channels) ? implode(',', array_values(array_unique($channels))) : (string)($order['channel_name'] ?? ''),
            'from_cache_count' => $cacheCount,
            'result_ids' => implode(',', $resultIds),
            'fail_reason' => $failReason,
            'refund_status' => $refundStatus,
            'refund_time' => $refundTime,
            'finish_time' => time(),
            'update_time' => time(),
        ]);

        $this->sendQueryNotice($orderId, $status, $failReason);

        return [
            'order_id' => $orderId,
            'results' => $results,
            'errors' => $errors,
            'status' => $status,
            'status_name' => HsxPhoneQueryOrderDict::getStatus()[$status] ?? '',
            'fail_reason' => $failReason,
        ];
    }

    private function validateQueryParams(array $params): void
    {
        if (empty($params['imeis']) || empty($params['id'])) {
            throw new CommonException('参数错误');
        }
    }

    private function parseImeiList(string $imeiString): array
    {
        $input = str_replace(["\r\n", "\r", "\n", "，", " "], ',', $imeiString);
        $imeis = array_values(array_filter(array_map('trim', explode(',', $input))));

        if (empty($imeis)) {
            throw new CommonException('请输入有效的串号');
        }

        return array_values(array_unique($imeis));
    }

    private function getQueryConfig(int $typeId, array $orderSnapshot = []): array
    {
        $useOrderSnapshot = !empty($orderSnapshot);
        $siteId = (int)($orderSnapshot['site_id'] ?? $this->site_id);
        $category = $this->categoryModel
            ->where('id', '=', $typeId)
            ->where(function ($query) use ($siteId) {
                $query->where('site_id', '=', $siteId)->whereOr('site_id', '=', 0);
            })
            ->field('*')
            ->order('site_id desc')
            ->find();

        if (empty($category)) {
            throw new CommonException('查询类型不存在');
        }
        if (!$useOrderSnapshot && (int)($category['is_show'] ?? 0) !== 1) {
            throw new CommonException('当前查询项目已下架，请刷新页面后重新选择');
        }

        $serviceCode = (string)($orderSnapshot['service_code'] ?? $category['service_code'] ?? '');
        $queryParam = (string)($orderSnapshot['query_param'] ?? $category['query_param'] ?? '');
        $categoryChannelKey = (string)($category['channel_key'] ?? '');
        $providerConfig = $this->getProviderConfig($siteId);
        if (!$useOrderSnapshot && empty($providerConfig['enabled'])) {
            throw new CommonException('手机查询服务已关闭');
        }
        $channel = $this->getProviderChannel($providerConfig, (string)($orderSnapshot['channel_key'] ?? ''));
        $activeChannelKey = (string)($channel['key'] ?? '');
        if ($categoryChannelKey !== '' && $activeChannelKey !== '' && $categoryChannelKey !== $activeChannelKey) {
            throw new CommonException('当前查询项目不属于已启用渠道，请刷新页面后重新选择');
        }
        $mapping = $this->getProviderMapping($providerConfig, $typeId, $serviceCode, (string)($channel['key'] ?? ''), $useOrderSnapshot);
        if ($useOrderSnapshot) {
            if (!empty($orderSnapshot['endpoint_type'])) {
                $mapping['endpoint_type'] = (string)$orderSnapshot['endpoint_type'];
            }
            if (!empty($orderSnapshot['endpoint_value'])) {
                $mapping['endpoint_value'] = (string)$orderSnapshot['endpoint_value'];
            }
        }
        if ($queryParam !== '' && empty($mapping['query_param'])) {
            $mapping['query_param'] = $queryParam;
        }

        if (empty($channel) || (!$useOrderSnapshot && empty($channel['enabled']))) {
            throw new CommonException('请先启用手机查询服务商渠道');
        }

        if (($channel['provider'] ?? '') === 'service_id_query' && (empty($channel['appid']) || empty($channel['secret']))) {
            throw new CommonException('请先配置爱查 AppID 和 Secret');
        }

        if (($channel['provider'] ?? '') === 'path_query' && empty($channel['token'])) {
            throw new CommonException('请先配置 3023 API Key');
        }

        if (($channel['provider'] ?? '') === 'path_query' && empty($mapping['endpoint_value'])) {
            throw new CommonException('请先配置 3023 查询接口路径');
        }
        if (($channel['provider'] ?? '') === 'service_id_query' && empty($mapping['endpoint_value'])) {
            throw new CommonException('当前渠道暂不支持该查询项目，请切换渠道或补充接口映射');
        }

        return [
            'name' => $orderSnapshot['service_name'] ?? $category['name'] ?? '',
            'service_code' => $serviceCode,
            'query_param' => (string)($mapping['query_param'] ?? $queryParam ?: ($channel['query_param'] ?? 'sn')),
            'price' => (float)($orderSnapshot['unit_price'] ?? $category['price'] ?? 0),
            'cost_price' => (float)($orderSnapshot['unit_cost'] ?? $mapping['cost_price'] ?? $category['cost_price'] ?? 0),
            'provider_config' => $providerConfig,
            'channel' => $channel,
            'mapping' => $mapping,
        ];
    }

    private function getCachedQueryResult(string $imei, array $order)
    {
        $query = $this->queryInfoModel->where([
            ['site_id', '=', (int)$order['site_id']],
            ['sn', '=', $imei],
            ['type_id', '=', (int)$order['type_id']],
            ['create_time', '>=', time() - self::CACHE_TTL],
        ]);

        if (!empty($order['service_code'])) {
            $query->where('service_code', '=', (string)$order['service_code']);
        }
        if (!empty($order['channel_key'])) {
            $query->where('channel_key', '=', (string)$order['channel_key']);
        }

        return $query->field('id, info')
            ->order('create_time desc')
            ->find();
    }

    private function insertQueryInfo(string $imei, array $order, string $infoJson): int
    {
        return (int)$this->queryInfoModel->insertGetId([
            'site_id' => (int)$order['site_id'],
            'order_id' => (int)($order['order_id'] ?? 0),
            'sn' => $imei,
            'type_id' => (int)$order['type_id'],
            'service_code' => (string)($order['service_code'] ?? ''),
            'channel_key' => (string)($order['channel_key'] ?? ''),
            'query_param' => (string)($order['query_param'] ?? ''),
            'member_id' => (int)$order['member_id'],
            'info' => $infoJson,
            'is_look' => 0,
            'pid' => (int)($order['pid'] ?? 0),
            'pay_type' => $order['pay_type'] ?? HsxPhoneQueryOrderDict::PAY_TYPE_MONEY,
            'money' => (float)($order['pay_money'] ?? 0) / max((int)($order['query_count'] ?? 1), 1),
            'create_time' => time(),
            'update_time' => time(),
        ]);
    }

    private function buildOrderSnapshot(array $queryConfig): array
    {
        $channel = $queryConfig['channel'] ?? [];
        $mapping = $queryConfig['mapping'] ?? [];

        return [
            'provider_name' => (string)($channel['provider'] ?? ''),
            'channel_key' => (string)($channel['key'] ?? ''),
            'channel_name' => (string)($channel['name'] ?? ''),
            'query_param' => (string)($mapping['query_param'] ?? $queryConfig['query_param'] ?? $channel['query_param'] ?? 'sn'),
            'endpoint_type' => (string)($mapping['endpoint_type'] ?? ''),
            'endpoint_value' => (string)($mapping['endpoint_value'] ?? ''),
        ];
    }

    private function deductPoint(int $point, int $relatedId): void
    {
        if ($point <= 0) {
            return;
        }

        $member = $this->memberModel
            ->where([
                ['site_id', '=', $this->site_id],
                ['member_id', '=', $this->member_id],
            ])
            ->field('point')
            ->find();

        if (!$member || (int)$member['point'] < $point) {
            throw new CommonException('积分不足');
        }

        $this->accountService->addLog(
            $this->site_id,
            $this->member_id,
            MemberAccountTypeDict::POINT,
            -$point,
            'hsx_phone_query',
            '积分消费查询',
            $relatedId
        );
    }

    private function refundPointIfNeeded(array $order, string $reason): bool
    {
        $payPoint = (int)($order['pay_point'] ?? 0);
        if (($order['pay_type'] ?? '') !== HsxPhoneQueryOrderDict::PAY_TYPE_POINT || $payPoint <= 0) {
            return false;
        }
        if ((int)($order['refund_status'] ?? 0) === 1) {
            return true;
        }

        try {
            $this->accountService->addLog(
                (int)$order['site_id'],
                (int)$order['member_id'],
                MemberAccountTypeDict::POINT,
                $payPoint,
                'hsx_phone_query_refund',
                '手机查询失败返还：' . mb_substr($reason, 0, 120),
                (int)$order['order_id']
            );
            return true;
        } catch (\Throwable $e) {
            Log::error('手机查询积分返还失败：order_id=' . (int)$order['order_id'] . ' ' . $e->getMessage());
            return false;
        }
    }

    private function refundMoneyIfNeeded(array $order, string $outTradeNo = '', string $reason = ''): bool
    {
        if (($order['pay_type'] ?? '') !== HsxPhoneQueryOrderDict::PAY_TYPE_MONEY || (float)($order['pay_money'] ?? 0) <= 0) {
            return false;
        }
        if ((int)($order['refund_status'] ?? 0) === 1) {
            return true;
        }

        $siteId = (int)($order['site_id'] ?? 0);
        $orderId = (int)($order['order_id'] ?? 0);
        if ($siteId <= 0 || $orderId <= 0) {
            return false;
        }

        try {
            if ($outTradeNo === '') {
                $pay = (new CorePayService())->findPayInfoByTrade($siteId, HsxPhoneQueryOrderDict::TRADE_TYPE, $orderId);
                $outTradeNo = (string)($pay['out_trade_no'] ?? '');
            }
            if ($outTradeNo === '') {
                throw new CommonException('支付流水不存在，无法退款');
            }

            $refundService = new CoreRefundService();
            $refundNo = $refundService->create(
                $siteId,
                $outTradeNo,
                (float)$order['pay_money'],
                '手机查询失败自动退款：' . mb_substr($reason ?: '查询失败', 0, 80),
                HsxPhoneQueryOrderDict::TRADE_TYPE,
                (string)$orderId
            );
            $refundService->refund($siteId, $refundNo, '', RefundDict::BACK, 'system', 0);

            $this->orderModel->where([
                ['site_id', '=', $siteId],
                ['order_id', '=', $orderId],
            ])->update([
                'refund_status' => 1,
                'refund_time' => time(),
                'update_time' => time(),
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::error('手机查询现金退款失败：order_id=' . $orderId . ' ' . $e->getMessage());
            return false;
        }
    }

    private function callDeviceQuery(array $queryConfig, string $code, int $id, array $order = []): array
    {
        $channel = $queryConfig['channel'] ?? [];
        $mapping = $queryConfig['mapping'] ?? [];
        $provider = (string)($channel['provider'] ?? '');

        if ($provider === 'path_query') {
            return $this->callPathQueryProvider($channel, $mapping, $code, $queryConfig, $order);
        }

        if ($provider === 'service_id_query') {
            return $this->callServiceIdQueryProvider($channel, $mapping, $code, $id, $queryConfig, $order);
        }

        throw new CommonException('不支持的手机查询服务商类型');
    }

    private function getProviderConfig(?int $siteId = null): array
    {
        return $this->providerChannelService->getConfig((int)($siteId ?: $this->site_id));
    }

    private function getProviderChannel(array $config, string $channelKey = ''): array
    {
        return $this->providerChannelService->getChannel($config, $channelKey);
    }

    private function getProviderMapping(array $config, int $typeId, string $serviceCode, string $channelKey, bool $includeDisabled = false): array
    {
        return $this->providerChannelService->getMapping($config, $typeId, $serviceCode, $channelKey, $includeDisabled);
    }

    private function callServiceIdQueryProvider(array $channel, array $mapping, string $code, int $id, array $queryConfig, array $order = []): array
    {
        $appid = (string)($channel['appid'] ?? '');
        $secret = (string)($channel['secret'] ?? '');
        $baseUrl = (string)($channel['base_url'] ?? self::API_URL);
        $serviceId = (string)($mapping['endpoint_value'] ?? $id);
        $serviceIdKey = (string)($channel['service_id_key'] ?? 'key');
        $queryParam = 'code';
        $method = (string)($channel['method'] ?? 'GET');
        $costPrice = (float)($mapping['cost_price'] ?? $queryConfig['cost_price'] ?? 0);

        $params = [
            'appid' => $appid,
            $queryParam => $code,
            $serviceIdKey => $serviceId,
            'style' => (string)($channel['style'] ?? self::API_STYLE),
            'time' => time(),
        ];

        $params['sign'] = $this->generateMd5Sign($params, $secret);
        $startedAt = microtime(true);
        $apiLogId = 0;
        try {
            $response = $this->httpRequest($method, $baseUrl, $params, [], (int)($channel['timeout'] ?? 30));
            $data = $this->normalizeProviderResponse($response);
            $apiLogId = $this->saveApiLog([
                'channel' => $channel,
                'mapping' => $mapping,
                'query_config' => $queryConfig,
                'order' => $order,
                'query_code' => $code,
                'request_method' => $method,
                'request_url' => $baseUrl,
                'request_params' => $this->maskRequestParams($params),
                'response' => $response,
                'cost_price' => $costPrice,
                'duration_ms' => $this->durationMs($startedAt),
                'status' => 'success',
            ]);
        } catch (\Throwable $e) {
            $this->saveApiLog([
                'channel' => $channel,
                'mapping' => $mapping,
                'query_config' => $queryConfig,
                'order' => $order,
                'query_code' => $code,
                'request_method' => $method,
                'request_url' => $baseUrl,
                'request_params' => $this->maskRequestParams($params),
                'response' => $response ?? [],
                'cost_price' => 0,
                'duration_ms' => $this->durationMs($startedAt),
                'status' => 'fail',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return [
            'code' => 200,
            'data' => $data,
            'third_cost' => $costPrice,
            'provider' => 'service_id_query',
            'channel_name' => (string)($channel['name'] ?? '爱查助手'),
            'api_log_id' => $apiLogId,
        ];
    }

    private function callPathQueryProvider(array $channel, array $mapping, string $code, array $queryConfig, array $order = []): array
    {
        $baseUrl = rtrim((string)($channel['base_url'] ?? ''), '/');
        $path = '/' . ltrim((string)($mapping['endpoint_value'] ?? ''), '/');
        if ($baseUrl === '' || $path === '/') {
            throw new CommonException('3023接口地址或路径未配置');
        }

        $params = ['sn' => $code];
        $headers = [];
        $requestParams = $params;
        if (($channel['auth_type'] ?? 'header') === 'query') {
            $authKey = (string)($channel['auth_key'] ?? 'key');
            $params[$authKey] = (string)($channel['token'] ?? '');
            $requestParams[$authKey] = '***';
        } else {
            $headers[] = ((string)($channel['auth_key'] ?? 'key')) . ': ' . (string)($channel['token'] ?? '');
        }
        $method = (string)($channel['method'] ?? 'GET');
        $costPrice = (float)($mapping['cost_price'] ?? $queryConfig['cost_price'] ?? 0);

        $startedAt = microtime(true);
        $apiLogId = 0;
        try {
            $response = $this->httpRequest($method, $baseUrl . $path, $params, $headers, (int)($channel['timeout'] ?? 30));
            $data = $this->normalizeProviderResponse($response);
            $apiLogId = $this->saveApiLog([
                'channel' => $channel,
                'mapping' => $mapping,
                'query_config' => $queryConfig,
                'order' => $order,
                'query_code' => $code,
                'request_method' => $method,
                'request_url' => $baseUrl . $path,
                'request_params' => $requestParams,
                'response' => $response,
                'cost_price' => $costPrice,
                'duration_ms' => $this->durationMs($startedAt),
                'status' => 'success',
            ]);
        } catch (\Throwable $e) {
            $this->saveApiLog([
                'channel' => $channel,
                'mapping' => $mapping,
                'query_config' => $queryConfig,
                'order' => $order,
                'query_code' => $code,
                'request_method' => $method,
                'request_url' => $baseUrl . $path,
                'request_params' => $requestParams,
                'response' => $response ?? [],
                'cost_price' => 0,
                'duration_ms' => $this->durationMs($startedAt),
                'status' => 'fail',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return [
            'code' => 200,
            'data' => $data,
            'third_cost' => $costPrice,
            'provider' => 'path_query',
            'channel_name' => (string)($channel['name'] ?? '3023Data'),
            'api_log_id' => $apiLogId,
        ];
    }

    private function saveApiLog(array $data): int
    {
        try {
            $channel = $data['channel'] ?? [];
            $mapping = $data['mapping'] ?? [];
            $queryConfig = $data['query_config'] ?? [];
            $order = $data['order'] ?? [];
            $response = $data['response'] ?? [];
            $time = time();

            return (int)(new HsxPhoneQueryApiLog())->insertGetId([
                'site_id' => (int)($order['site_id'] ?? $this->site_id),
                'order_id' => (int)($order['order_id'] ?? 0),
                'result_id' => 0,
                'member_id' => (int)($order['member_id'] ?? $this->member_id),
                'type_id' => (int)($order['type_id'] ?? 0),
                'service_code' => (string)($queryConfig['service_code'] ?? $mapping['service_code'] ?? ''),
                'service_name' => (string)($queryConfig['name'] ?? ''),
                'provider_key' => (string)($channel['provider'] ?? ''),
                'provider_name' => (string)($channel['name'] ?? ''),
                'channel_key' => (string)($channel['key'] ?? ''),
                'channel_name' => (string)($channel['name'] ?? ''),
                'endpoint_type' => (string)($mapping['endpoint_type'] ?? ''),
                'endpoint_value' => (string)($mapping['endpoint_value'] ?? ''),
                'query_param' => (string)($mapping['query_param'] ?? $channel['query_param'] ?? ''),
                'query_code' => (string)($data['query_code'] ?? ''),
                'request_method' => strtoupper((string)($data['request_method'] ?? 'GET')),
                'request_url' => (string)($data['request_url'] ?? ''),
                'request_params' => $this->encodeLogJson($data['request_params'] ?? []),
                'response_code' => $this->readResponseCode($response),
                'response_message' => $this->readResponseMessage($response),
                'response_data' => $this->encodeLogJson($response),
                'cost_price' => round((float)($data['cost_price'] ?? 0), 3),
                'duration_ms' => (int)($data['duration_ms'] ?? 0),
                'status' => (string)($data['status'] ?? 'success'),
                'error_message' => mb_substr((string)($data['error_message'] ?? ''), 0, 1000),
                'create_time' => $time,
                'update_time' => $time,
            ]);
        } catch (\Throwable $e) {
            Log::error('手机查询接口日志写入失败：' . $e->getMessage());
            return 0;
        }
    }

    private function bindApiLogResult(int $apiLogId, int $resultId, int $siteId): void
    {
        if ($apiLogId <= 0 || $resultId <= 0 || $siteId <= 0) {
            return;
        }

        try {
            (new HsxPhoneQueryApiLog())->where([
                ['id', '=', $apiLogId],
                ['site_id', '=', $siteId],
            ])->update([
                'result_id' => $resultId,
                'update_time' => time(),
            ]);
        } catch (\Throwable $e) {
            Log::error('手机查询接口日志绑定结果失败：' . $e->getMessage());
        }
    }

    private function maskRequestParams(array $params): array
    {
        foreach (['secret', 'sign', 'token', 'key'] as $sensitiveKey) {
            if (array_key_exists($sensitiveKey, $params)) {
                $params[$sensitiveKey] = '***';
            }
        }

        return $params;
    }

    private function durationMs(float $startedAt): int
    {
        return max(0, (int)round((microtime(true) - $startedAt) * 1000));
    }

    private function readResponseCode($response): string
    {
        if (!is_array($response)) {
            return '';
        }

        return (string)($response['code'] ?? $response['status'] ?? '');
    }

    private function readResponseMessage($response): string
    {
        if (!is_array($response)) {
            return '';
        }

        return mb_substr((string)($response['message'] ?? $response['msg'] ?? ''), 0, 500);
    }

    private function encodeLogJson($data): string
    {
        if ($data === null || $data === '') {
            return '';
        }

        if (!is_array($data)) {
            return (string)$data;
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function httpRequest(string $method, string $url, array $params, array $headers = [], int $timeout = 30): array
    {
        $method = strtoupper($method ?: 'GET');
        $ch = curl_init();
        if ($method === 'GET') {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $raw = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new CommonException('请求失败: ' . $error);
        }
        curl_close($ch);

        $response = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($response)) {
            throw new CommonException('查询接口返回异常');
        }

        return $response;
    }

    private function normalizeProviderResponse(array $response): array
    {
        $response = is_string($response) ? json_decode($response, true) : $response;

        if (!is_array($response)) {
            throw new CommonException('查询接口返回异常');
        }

        $code = (int)($response['code'] ?? $response['status'] ?? 200);
        $success = $response['success'] ?? null;
        if (($success === false) || (!in_array($code, [0, 1, 200], true))) {
            throw new CommonException($response['message'] ?? $response['msg'] ?? '查询失败');
        }

        return $response['data'] ?? $response['result'] ?? $response;
    }

    private function generateMd5Sign(array $params, string $secret): string
    {
        ksort($params);
        $signParams = array_filter($params, static fn($value) => $value !== '' && $value !== null);
        $queryString = http_build_query($signParams);
        $queryString .= '&secret=' . $secret;
        return md5($queryString);
    }

    private function buildUserQueryListModel(array $params)
    {
        $field = 'id, sn, type_id, pid, info, create_time, is_look, pay_type, money';

        $query = $this->queryInfoModel->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
        ]);

        if (!empty($params['keyword'])) {
            $query->where('sn', 'like', "%{$params['keyword']}%");
        }

        if (!empty($params['pid'])) {
            $query->where('pid', '=', (int)$params['pid'] - 1);
        }

        if (!empty($params['start_time'])) {
            $query->where('create_time', '>=', strtotime($params['start_time']));
        }

        if (!empty($params['end_time'])) {
            $query->where('create_time', '<=', strtotime($params['end_time'] . ' 23:59:59'));
        }

        return $query
            ->field($field)
            ->append(['type_name'])
            ->order([
                'is_look' => 'asc',
                'create_time' => 'desc',
            ]);
    }

    private function buildUserOrderListModel(array $params)
    {
        $field = 'order_id,order_no,imeis,type_id,pid,service_name,channel_key,channel_name,pay_type,unit_price,pay_money,pay_point,query_count,success_count,fail_count,status,refund_status,result_ids,fail_reason,create_time,pay_time,finish_time,refund_time';

        $query = $this->orderModel->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
        ])->where('status', '<>', HsxPhoneQueryOrderDict::WAIT_PAY);

        if (!empty($params['keyword'])) {
            $keyword = trim((string)$params['keyword']);
            $query->where(function ($query) use ($keyword) {
                $query->where('imeis', 'like', "%{$keyword}%")
                    ->whereOr('order_no', 'like', "%{$keyword}%")
                    ->whereOr('service_name', 'like', "%{$keyword}%");
            });
        }

        if ($params['pid'] !== '' && $params['pid'] !== null) {
            $query->where('pid', '=', (int)$params['pid']);
        }

        if (!empty($params['start_time'])) {
            $query->where('create_time', '>=', strtotime($params['start_time']));
        }

        if (!empty($params['end_time'])) {
            $query->where('create_time', '<=', strtotime($params['end_time'] . ' 23:59:59'));
        }

        return $query
            ->field($field)
            ->append(['status_name'])
            ->order('create_time desc');
    }

    private function formatOrderResults(array $data): array
    {
        foreach ($data as &$item) {
            $resultIds = $this->parseIdList((string)($item['result_ids'] ?? ''));
            $firstResultId = (int)($resultIds[0] ?? 0);
            $imeis = $this->parseCodeList((string)($item['imeis'] ?? ''));
            $firstCode = (string)($imeis[0] ?? '');
            $isFail = (int)($item['status'] ?? 0) === HsxPhoneQueryOrderDict::FAIL;

            $item['id'] = $firstResultId;
            $item['result_id'] = $firstResultId;
            $item['result_ids'] = $resultIds;
            $item['sn'] = count($imeis) > 1 ? $firstCode . ' 等' . count($imeis) . '个' : $firstCode;
            $item['type_name'] = (string)($item['service_name'] ?? '');
            $item['info'] = $firstResultId > 0 ? $this->getResultInfo($firstResultId) : [];
            $display = $this->resultFormatter->format($item['info'], [
                'type_name' => $item['type_name'],
                'create_time' => $this->formatTimestamp($item['create_time'] ?? 0),
            ]);
            $item['display_info'] = $display['fields'];
            $item['display_summary'] = $display['summary'];
            $item['display_status_tags'] = $display['status_tags'];
            $item['display_title'] = $display['title'];
            $item['display_subtitle'] = $display['subtitle'];
            $item['display_image'] = $display['image'];
            $item['is_look'] = $firstResultId > 0 ? $this->getResultLookStatus($firstResultId) : 1;
            $item['create_time'] = $this->formatTimestamp($item['create_time']);
            $item['pay_time'] = $this->formatTimestamp($item['pay_time'] ?? 0);
            $item['finish_time'] = $this->formatTimestamp($item['finish_time'] ?? 0);
            $item['refund_time'] = $this->formatTimestamp($item['refund_time'] ?? 0);
            $item['pay_text'] = $item['pay_type'] === HsxPhoneQueryOrderDict::PAY_TYPE_POINT
                ? ((int)($item['pay_point'] ?? 0) . '积分')
                : ('￥' . number_format((float)($item['pay_money'] ?? 0), 2));
            $item['refund_text'] = $this->buildRefundText($item);
            $item['can_view_detail'] = $firstResultId > 0 && !$isFail;
            $item['fail_reason'] = mb_substr((string)($item['fail_reason'] ?? ''), 0, 160);
        }

        return $data;
    }

    private function parseIdList(string $ids): array
    {
        return array_values(array_filter(array_map('intval', explode(',', $ids))));
    }

    private function parseCodeList(string $codeString): array
    {
        $input = str_replace(["\r\n", "\r", "\n", "，", " "], ',', $codeString);
        return array_values(array_filter(array_map('trim', explode(',', $input))));
    }

    private function getResultInfo(int $resultId): array
    {
        if ($resultId <= 0) {
            return [];
        }

        $info = $this->queryInfoModel->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['id', '=', $resultId],
        ])->value('info');

        return $this->formatJsonField($info);
    }

    private function getResultLookStatus(int $resultId): int
    {
        if ($resultId <= 0) {
            return 1;
        }

        return (int)$this->queryInfoModel->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['id', '=', $resultId],
        ])->value('is_look');
    }

    private function buildRefundText(array $order): string
    {
        if ((int)($order['status'] ?? 0) !== HsxPhoneQueryOrderDict::FAIL) {
            return '';
        }
        if ((int)($order['refund_status'] ?? 0) === 1) {
            return ($order['pay_type'] ?? '') === HsxPhoneQueryOrderDict::PAY_TYPE_POINT ? '积分已退还' : '已原路退还';
        }

        return ($order['pay_type'] ?? '') === HsxPhoneQueryOrderDict::PAY_TYPE_POINT ? '积分退还处理中' : '退款处理中';
    }

    private function formatQueryResults(array $data): array
    {
        foreach ($data as &$item) {
            $item['info'] = $this->formatJsonField($item['info']);
            $item['create_time'] = $this->formatTimestamp($item['create_time']);
        }

        return $data;
    }

    private function formatJsonField($data): array
    {
        if (empty($data)) {
            return [];
        }

        return is_array($data) ? $data : (json_decode($data, true) ?: []);
    }

    private function formatTimestamp($timestamp): string
    {
        if (empty($timestamp)) {
            return '';
        }

        return is_numeric($timestamp) ? date('Y-m-d H:i:s', (int)$timestamp) : $timestamp;
    }

    private function updateViewStatus(int $id): bool
    {
        $isLook = $this->queryInfoModel->where([['id', '=', $id]])->value('is_look');
        if ($isLook == 1) {
            return true;
        }

        return (bool)$this->queryInfoModel->where([['id', '=', $id]])->update(['is_look' => 1]);
    }

    private function sendQueryNotice(int $orderId, int $status, string $failReason = ''): void
    {
        $key = $status == HsxPhoneQueryOrderDict::SUCCESS ? 'hsx_phone_query_success' : 'hsx_phone_query_fail';
        $order = $this->orderModel->where('order_id', $orderId)->findOrEmpty();
        if ($order->isEmpty()) {
            return;
        }

        try {
            NoticeService::send((int)$order['site_id'], $key, [
                'order_id' => $orderId,
                'fail_reason' => $failReason,
            ]);
            $order->save([
                'notice_status' => 1,
                'notice_time' => time(),
                'notice_error' => '',
            ]);
        } catch (\Throwable $e) {
            $order->save([
                'notice_status' => 2,
                'notice_error' => $e->getMessage(),
            ]);
            Log::error('手机查询通知发送失败：' . $e->getMessage());
        }
    }

    private function createOrderNo(): string
    {
        return date('YmdHis') . random_int(100000, 999999);
    }
}
