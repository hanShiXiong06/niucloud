<?php
declare(strict_types=1);
namespace addon\hsx_express\app\service\core;

use addon\hsx_express\app\support\Cipher;
use addon\hsx_express\app\support\OperationLock;
use core\exception\CommonException;

/** 顺丰直连单包裹面单：先保存原运单，再取 PDF；不修改商城、库存及资金。 */
final class SfWaybillService
{
    private TaskRepository $repository;
    private $requester;
    private $configLoader;
    private $lockRunner;
    private $operationGuard;
    private SfPdfDownloader $downloader;

    public function __construct(?TaskRepository $repository = null, ?callable $requester = null, ?callable $configLoader = null,
        ?callable $lockRunner = null, ?callable $operationGuard = null, ?SfPdfDownloader $downloader = null)
    {
        $this->repository = $repository ?? new TaskRepository();
        $this->requester = $requester ?? static fn($service, $data, $config) => (new SfClient())->request($service, $data, $config);
        $this->configLoader = $configLoader ?? static fn($site) => (new SfConfigService())->get($site, 'waybill');
        $this->lockRunner = $lockRunner ?? static fn($site, $key, $fn) => OperationLock::run($site, $key, $fn);
        $this->operationGuard = $operationGuard ?? static fn($data) => event('HsxExpressTaskOperationGuard', $data);
        $this->downloader = $downloader ?? new SfPdfDownloader();
    }

    public function execute(int $siteId, string $operation, array $payload): array
    {
        if ($siteId <= 0) throw new CommonException('物流操作必须归属具体站点');
        if ($operation === 'create') return $this->create($siteId, $payload);
        $task = !empty($payload['task_id']) ? $this->repository->find($siteId, (int)$payload['task_id'])
            : $this->repository->findBusiness($siteId, (string)($payload['business_type'] ?? ''), (string)($payload['business_id'] ?? ''));
        if (!$task && $operation === 'query') return [];
        $this->assertTask($task);
        if ($operation === 'query') return self::view($task);
        if (!in_array($operation, ['refresh', 'reprint', 'cancel'], true)) throw new CommonException('顺丰任务不支持此操作；请核实原单，不要重新下单恢复');
        return ($this->lockRunner)($siteId, self::lockKey($task), function () use ($siteId, $task, $operation, $payload) {
            $fresh = $this->repository->find($siteId, (int)$task['id']);
            $this->assertTask($fresh);
            if ($operation === 'refresh') return $this->refresh($fresh);
            if ($operation === 'cancel') return $this->cancel($fresh, $payload);
            if (!self::view($fresh)['can_reprint']) throw new CommonException('当前任务不能重新获取 PDF，请先核实原运单状态');
            return $this->fetchPdf($fresh, true);
        });
    }

    private function create(int $siteId, array $payload): array
    {
        $payment = $payload['freight_payment'] ?? 'receiver';
        if (!in_array($payment, ['receiver', 'sender'], true)) throw new CommonException('请选择到付或寄方付，未申请运单');
        $payload = WaybillProtocol::normalizePayload($payload);
        $payload['freight_payment'] = $payment;
        return ($this->lockRunner)($siteId, self::lockKey($payload + ['business_order_id' => $payload['order_id']]), function () use ($siteId, $payload) {
            $existing = $this->repository->findBusiness($siteId, $payload['business_type'], $payload['business_id']);
            if ($existing && !in_array($existing['state'], ['failed', 'cancelled'], true)) {
                return WaybillTaskDispatcher::view($existing) + ['reused' => true];
            }
            foreach ($this->repository->activeForOrder($siteId, $payload['business_type'], $payload['order_id']) as $active) {
                $refs = json_decode((string)($active['business_refs_json'] ?? ''), true) ?: [];
                if (array_intersect($payload['order_goods_ids'], $refs['order_goods_ids'] ?? [])) throw new CommonException('所选商品已有未取消的物流任务，请处理原单，不能跨渠道重复取号');
            }
            $config = ($this->configLoader)($siteId);
            if (empty($config['enabled'])) throw new CommonException('本站顺丰电子面单尚未启用，请完成独立面单配置');
            $config = SfConfigService::withWaybillPayment($config, $payload['freight_payment']);
            $check = SfConfigService::readiness($config, 'waybill');
            if (empty($check['ready'])) throw new CommonException('顺丰电子面单配置未就绪，请检查账号、产品、月结与 PDF 模板配置');
            $taskNo = 'SF' . date('YmdHis') . bin2hex(random_bytes(8));
            try { $params = SfProtocol::createOrder($config, $payload, $taskNo, 'waybill'); }
            catch (ProviderException $e) { throw new CommonException($e->getMessage()); }
            $snapshot = ['version' => 1, 'scene' => 'waybill', 'config' => $config, 'payload' => $payload, 'pdf' => []];
            $task = $this->repository->create([
                'site_id' => $siteId, 'task_no' => $taskNo, 'business_type' => $payload['business_type'], 'business_id' => $payload['business_id'],
                'attempt' => (int)($existing['attempt'] ?? 0) + 1, 'business_order_id' => $payload['order_id'],
                'business_refs_json' => json_encode(['order_id' => $payload['order_id'], 'order_goods_ids' => $payload['order_goods_ids'],
                    'order_no' => $payload['business_no'], 'environment' => $config['environment'] ?? 'sandbox',
                    'freight_payment' => $payload['freight_payment'],
                    'monthly_card_tail' => $payload['freight_payment'] === 'sender' ? substr($config['monthly_card'], -4) : ''], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'payload_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                'provider' => 'sf_direct', 'carrier' => 'shunfeng', 'exp_type' => (string)($config['product_code'] ?? ''), 'print_type' => 'PDF',
                'state' => 'creating', 'snapshot_cipher' => self::encode($snapshot), 'logs_json' => '[]', 'label' => '',
                'create_at' => time(), 'update_at' => time(), 'submitted_at' => time(),
                'message' => '已保存顺丰取号任务，正在提交；请勿重复操作',
            ]);
            try {
                $result = ($this->requester)(SfClient::CREATE_ORDER, $params, $config);
                $order = SfProtocol::normalizeOrder($result);
            } catch (ProviderException $e) {
                return self::view($this->repository->update($task, ['state' => $e->isUnknown() ? 'unknown' : 'failed',
                    'message' => ($e->isUnknown() ? '顺丰取号结果待核实：' : '顺丰未受理：') . self::safeReason($e->getMessage(), $snapshot)
                        . ($e->isUnknown() ? '；请查询原单，不能重复申请或换渠道' : '；请检查配置与订单资料')], 'sf_create_unconfirmed'));
            } catch (\Throwable $e) {
                return self::view($this->repository->update($task, ['state' => 'unknown', 'message' => '顺丰响应未能确认，原任务保留，请查询原单，勿重复取号'], 'sf_create_unknown'));
            }
            if (!empty($order['definitive_rejected']) && empty($order['waybill_no'])) {
                return self::view($this->repository->update($task, ['state' => 'failed',
                    'message' => '顺丰明确未受理：' . self::safeReason((string)($order['message'] ?? '请检查配置与订单资料'), $snapshot)], 'sf_create_rejected'));
            }
            if (!$this->matchesOrder($task, $order) || empty($order['confirmed']) || empty($order['waybill_no']) || empty($order['success'])) {
                return self::view($this->repository->update($task, ['state' => 'unknown', 'message' => '顺丰原单待核实：'
                    . self::safeReason((string)($order['message'] ?? '尚未返回与原申请一致的完整运单'), $snapshot) . '；请查询原单，勿重复下单'], 'sf_create_unknown'));
            }
            // PDF 是第二步：此处先提交运单事实，PDF 超时/拒绝都无权将原单改为 failed。
            $task = $this->repository->update($task, ['state' => 'print_pending', 'provider_order_no' => (string)$order['order_no'],
                'waybill_no' => (string)$order['waybill_no'], 'provider_task_id' => (string)($result['_response_id'] ?? ''),
                'message' => '顺丰已取号，正在获取原单 PDF；尚未确认交件'], 'sf_order_created');
            return $this->fetchPdf($task);
        });
    }

    private function refresh(array $task): array
    {
        if (in_array($task['state'], ['cancelled', 'failed'], true)) return self::view($task);
        $snapshot = self::snapshot($task);
        try {
            $result = ($this->requester)(SfClient::SEARCH_ORDER, SfProtocol::searchOrder((string)$task['task_no']), $snapshot['config']);
            $order = SfProtocol::normalizeOrder($result);
        } catch (\Throwable $e) {
            $reason = $e instanceof ProviderException ? self::safeReason($e->getMessage(), $snapshot) : '查询响应暂不可用';
            return self::view($this->repository->update($task, ['message' => '原单查询未确认：' . $reason . '；保留原状态，未重新下单'], 'sf_query_unavailable'));
        }
        if (!$this->matchesOrder($task, $order) || empty($order['success'])) {
            return self::view($this->repository->update($task, ['message' => '顺丰查询未确认原申请：' . self::safeReason((string)($order['message'] ?? ''), $snapshot) . '；保留原任务，请人工核实，不要重新取号'], 'sf_query_unconfirmed'));
        }
        if (!empty($order['cancelled'])) {
            return self::view($this->repository->update($task, ['state' => 'cancelled', 'message' => '顺丰查询已确认原运单取消；商城订单、退款和库存未改变'], 'sf_cancel_verified'));
        }
        if (in_array($task['state'], ['cancelling', 'cancel_unknown'], true)) {
            return self::view($this->repository->update($task, ['state' => 'cancel_unknown', 'message' => '查到原运单，但尚不能确认取消结果；请联系顺丰核实，不要另开运单'], 'sf_cancel_unconfirmed'));
        }
        if (empty($order['confirmed']) || empty($order['waybill_no'])) {
            return self::view($this->repository->update($task, ['message' => '顺丰尚未返回完整原单结果；未重新取号，未获取 PDF'], 'sf_query_unconfirmed'));
        }
        if (!empty($task['waybill_no']) && !hash_equals((string)$task['waybill_no'], (string)$order['waybill_no'])) {
            return self::view($this->repository->update($task, ['state' => 'unknown', 'message' => '顺丰返回运单号与原任务冲突，请人工核实；未覆盖原运单'], 'sf_order_conflict'));
        }
        $ready = self::pdfMeta($task)['ready'];
        return self::view($this->repository->update($task, ['provider_order_no' => (string)$order['order_no'], 'waybill_no' => (string)$order['waybill_no'],
            'state' => $ready ? 'ready' : 'print_failed', 'message' => $ready ? '已核实原顺丰运单；PDF 可下载，是否出纸仍需人工确认' : '已核实原顺丰运单，请重新获取原单 PDF；未重新下单'], 'sf_order_queried'));
    }

    private function fetchPdf(array $task, bool $repeat = false): array
    {
        $snapshot = self::snapshot($task);
        if (empty($task['waybill_no']) || empty($task['provider_order_no'])) throw new CommonException('尚未确认原运单，不能获取 PDF');
        $templateChanged = false;
        if ($repeat) {
            // A template correction is safe only within the same original account/environment.
            // Keep credentials, billing, original address and waybill immutable.
            try { $current = ($this->configLoader)((int)$task['site_id']); }
            catch (\Throwable $e) { $current = []; }
            $template = trim((string)($current['template_code'] ?? ''));
            if ($template !== '' && $template !== (string)($snapshot['config']['template_code'] ?? '')
                && (string)($current['environment'] ?? '') === (string)($snapshot['config']['environment'] ?? '')
                && hash_equals((string)($snapshot['config']['client_code'] ?? ''), (string)($current['client_code'] ?? ''))) {
                $snapshot['config']['template_code'] = $template;
                $snapshot['pdf'] = [];
                $templateChanged = true;
            }
        }
        $changes = ['state' => 'print_pending', 'reprint_count' => (int)($task['reprint_count'] ?? 0) + ($repeat ? 1 : 0),
            'message' => ($templateChanged ? '正在使用当前同账号模板获取原运单 PDF' : '正在获取原运单 PDF') . '；此操作不会重新取号，也不会发送设备打印任务'];
        if ($templateChanged) $changes += ['snapshot_cipher' => self::encode($snapshot), 'label' => ''];
        $task = $this->repository->update($task, $changes, 'sf_pdf_requested');
        try {
            $result = ($this->requester)(SfClient::PRINT_PDF, SfProtocol::pdfRequest($snapshot['config'], $task, $snapshot['payload']), $snapshot['config']);
            $artifact = SfProtocol::normalizePdf($result, (string)$task['waybill_no']);
            if (empty($artifact['url']) || empty($artifact['token']) || (int)($artifact['expires_at'] ?? 0) <= time()) throw new ProviderException('PDF 内容不完整', false);
            $snapshot['pdf'] = $artifact;
            return self::view($this->repository->update($task, ['state' => 'ready', 'snapshot_cipher' => self::encode($snapshot),
                'label' => json_encode(['format' => 'pdf', 'expires_at' => (int)$artifact['expires_at']], JSON_THROW_ON_ERROR),
                'message' => ($templateChanged ? '已使用当前同账号模板生成原单 PDF' : '原单 PDF 已生成') . '，请下载并用电脑打印；出纸和实际交件需要人工确认'], 'sf_pdf_ready'));
        } catch (\Throwable $e) {
            $reason = $e instanceof ProviderException ? self::safeReason($e->getMessage(), $snapshot) : '响应暂不可用';
            return self::view($this->repository->update($task, ['state' => 'print_failed', 'message' => '顺丰运单已保留，PDF 获取失败：' . $reason . '；请重新获取原单 PDF，不要重新申请运单'], 'sf_pdf_unavailable'));
        }
    }

    private function cancel(array $task, array $payload): array
    {
        if ($task['state'] === 'cancelled') return self::view($task);
        if (!self::view($task)['can_cancel']) throw new CommonException('原运单或取消结果尚不明确，请先核实；未执行取消');
        $reason = trim((string)($payload['reason'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > 200) throw new CommonException('请填写1至200字取消原因');
        $guard = ($this->operationGuard)(['site_id' => (int)$task['site_id'], 'operation' => 'cancel', 'task' => self::view($task)]);
        if (!is_array($guard) || !in_array(true, $guard, true)) throw new CommonException('未取得原业务取消许可，无法核实是否交件，未调用顺丰');
        $snapshot = self::snapshot($task);
        $previousState = self::view($task)['state'];
        $task = $this->repository->update($task, ['state' => 'cancelling', 'message' => '正在取消原顺丰运单，不取消订单、不退款、不恢复库存；取消原因：' . self::safeReason($reason, $snapshot)], 'sf_cancel_requested');
        $failureReason = '尚未收到对应原单的明确取消确认';
        try {
            $result = ($this->requester)(SfClient::CANCEL_ORDER, SfProtocol::cancelOrder((string)$task['provider_order_no'], (string)$task['waybill_no'], $reason), $snapshot['config']);
            $cancel = SfProtocol::normalizeCancel($result, (string)$task['provider_order_no']);
            $confirmed = ($cancel['confirmed'] ?? false) === true
                && (empty($cancel['order_no']) || hash_equals((string)$task['provider_order_no'], (string)$cancel['order_no']));
            $failureReason = self::safeReason((string)($cancel['message'] ?? $failureReason), $snapshot);
        } catch (ProviderException $e) {
            $failureReason = self::safeReason($e->getMessage(), $snapshot);
            if (!$e->isUnknown()) return self::view($this->repository->update($task, ['state' => $previousState,
                'message' => '本次取消未执行：' . $failureReason . '；保留原运单状态，请处理原因后重试取消同一原单'], 'sf_cancel_rejected'));
            $confirmed = false;
        } catch (\Throwable $e) { $confirmed = false; }
        return self::view($this->repository->update($task, ['state' => $confirmed ? 'cancelled' : 'cancel_unknown',
            'message' => $confirmed ? '顺丰已确认取消原运单；商城订单、退款与库存未改变' : '顺丰取消结果待核实：' . $failureReason . '；请重试取消原单或联系顺丰确认，不能另开运单'], $confirmed ? 'sf_cancelled' : 'sf_cancel_unknown'));
    }

    public function assertDeliveryAllowed(int $siteId, array $payload): void
    {
        $number = trim((string)($payload['express_number'] ?? ''));
        if ($number === '') return;
        $tasks = array_filter($this->repository->findWaybill($siteId, $number), static fn($task) => ($task['provider'] ?? '') === 'sf_direct');
        foreach ($tasks as $task) {
            $view = self::view($task);
            if (($view['environment'] ?? 'sandbox') !== 'production') throw new CommonException('此运单来自本站顺丰沙箱测试，不能通过手工填写用于正式发货');
            if (empty($view['can_confirm_delivery'])) throw new CommonException('此顺丰运单已取消或结果待核实，不能通过手工填写绕过原任务核验');
        }
        foreach ($tasks as $task) {
            if (($task['business_type'] ?? '') !== 'phone_shop' || (int)($task['business_order_id'] ?? 0) <= 0
                || (int)$task['business_order_id'] !== (int)($payload['order_id'] ?? 0)) throw new CommonException('此顺丰运单不属于本次商城订单，不能借用其他订单的原单发货');
            $refs = json_decode((string)($task['business_refs_json'] ?? ''), true) ?: [];
            $expected = self::goodsIds($refs['order_goods_ids'] ?? []);
            $selected = self::goodsIds($payload['waybill_order_goods_ids'] ?? $payload['order_goods_ids'] ?? []);
            if (!$expected || $expected !== $selected) throw new CommonException('此顺丰运单商品范围与本次发货不一致，请使用原包裹的商品确认交件');
        }
    }

    public function downloadPdf(int $siteId, int $id): array
    {
        $task = $this->repository->find($siteId, $id);
        $this->assertTask($task);
        return ($this->lockRunner)($siteId, self::lockKey($task), function () use ($siteId, $id) {
            $task = $this->repository->find($siteId, $id);
            $this->assertTask($task);
            if (!self::view($task)['can_download']) throw new CommonException('原任务 PDF 不可下载、已过期或正在取消，请先核实任务并重新获取原单 PDF');
            try { $file = $this->downloader->download(self::snapshot($task)['pdf'] ?? []); }
            catch (ProviderException $e) { throw new CommonException($e->getMessage()); }
            $this->repository->update($task, [], 'sf_pdf_downloaded');
            return $file + ['filename' => 'sf-waybill-' . (int)$task['id'] . '.pdf'];
        });
    }

    public static function view(array $task): array
    {
        $fields = ['id', 'site_id', 'task_no', 'business_type', 'business_id', 'attempt', 'carrier', 'exp_type', 'print_type',
            'provider_task_id', 'waybill_no', 'message', 'last_code', 'reprint_count', 'create_at', 'update_at', 'submitted_at', 'last_callback_at'];
        $view = array_intersect_key($task, array_flip($fields));
        $state = (string)$task['state'];
        if ($state === 'creating' && time() - (int)$task['update_at'] > 90) $state = 'unknown';
        if ($state === 'cancelling' && time() - (int)$task['update_at'] > 90) $state = 'cancel_unknown';
        $names = ['creating' => '顺丰取号中', 'unknown' => '顺丰取号结果待核实', 'failed' => '顺丰未受理', 'ready' => '原单 PDF 已生成',
            'print_pending' => '已取号，PDF 获取中', 'print_failed' => '已取号，PDF 待获取', 'cancelling' => '顺丰取消中',
            'cancel_unknown' => '顺丰取消结果待核实', 'cancelled' => '顺丰运单已取消'];
        $refs = json_decode((string)($task['business_refs_json'] ?? '{}'), true) ?: [];
        $pdf = self::pdfMeta($task);
        $hasWaybill = !empty($task['waybill_no']) && !empty($task['provider_order_no']);
        $valid = $hasWaybill && in_array($state, ['ready', 'print_pending', 'print_failed'], true);
        $canCancel = $valid || ($state === 'cancel_unknown' && $hasWaybill);
        // 明确未受理、处理中、已取消与结果未知不是同一种处理路径。
        $cancelReasons = [
            'failed' => '本次申请未被顺丰受理，没有运单需要取消；修正提示中的配置后可重新申请。',
            'creating' => '正在申请运单，请等待返回结果，不要重复提交。',
            'cancelling' => '取消请求处理中，请等待结果，不要重复提交。',
            'cancelled' => '原运单已取消，无需再次取消。',
        ];
        $environment = (string)($refs['environment'] ?? 'sandbox');
        return $view + ['task_id' => (int)$task['id'], 'provider' => 'sf_direct', 'provider_key' => 'hsx_express_sf_direct',
            'provider_code' => 'hsx_express_sf_direct', 'carrier_code' => 'shunfeng', 'carrier_name' => '顺丰速运',
            'exp_type_name' => ['1' => '顺丰特快', '2' => '顺丰标快'][(string)($task['exp_type'] ?? '')] ?? '顺丰产品（以原单为准）',
            'carrier_mapping' => ['field' => 'express_no', 'code' => 'SF'], 'state' => $state, 'state_name' => $names[$state] ?? '顺丰状态待核实',
            'business_refs' => $refs, 'business_no' => (string)($refs['order_no'] ?? ''), 'environment' => $environment,
            'freight_payment' => $refs['freight_payment'] ?? '', 'monthly_card_tail' => $refs['monthly_card_tail'] ?? '',
            'freight_payment_label' => ['receiver' => '到付 · 收件人付运费', 'sender' => '寄方付 · 本站月结'][$refs['freight_payment'] ?? ''] ?? '原单付款方式未记录，请以顺丰面单为准',
            'can_confirm_delivery' => $valid && $environment === 'production', 'success' => $valid || $state === 'cancelled',
            'label' => '', 'labels' => [], 'label_state' => $pdf['ready'] ? 'ready' : ($pdf['expires_at'] ? 'expired' : 'unavailable'),
            'pdf_expires_at' => $pdf['expires_at'], 'can_download' => $valid && $pdf['ready'],
            'pdf_download_path' => $valid && $pdf['ready'] ? 'hsx_express/tasks/' . (int)$task['id'] . '/pdf' : '',
            'can_refresh' => !in_array($state, ['cancelled', 'failed'], true), 'can_recover' => false,
            'can_reprint' => $valid && ($state !== 'print_pending' || time() - (int)$task['update_at'] > 90),
            'can_cancel' => $canCancel, 'cancel_unavailable_reason' => $canCancel ? '' : ($cancelReasons[$state] ?? '先确认原运单及取消结果，不能在状态不明时重复下单'),
            'verification_help' => '刷新只查询原顺丰订单；取消待核实可重试取消同一原单，不会新建运单。PDF 失败只重取原单文件；沙箱运单不能用于正式商城发货。'];
    }

    private static function pdfMeta(array $task): array
    {
        $data = json_decode((string)($task['label'] ?? ''), true);
        $expiry = is_array($data) && ($data['format'] ?? '') === 'pdf' ? (int)($data['expires_at'] ?? 0) : 0;
        return ['ready' => $expiry > time(), 'expires_at' => $expiry];
    }

    private function matchesOrder(array $task, array $order): bool
    {
        return !empty($order['order_no']) && hash_equals((string)$task['task_no'], (string)$order['order_no']);
    }

    private function assertTask(array $task): void
    {
        if (!$task || ($task['provider'] ?? '') !== 'sf_direct') throw new CommonException('任务不存在、不是顺丰直连或不属于本站');
    }

    private static function snapshot(array $task): array
    {
        $data = json_decode(Cipher::decrypt((string)$task['snapshot_cipher']), true);
        if (!is_array($data) || ($data['scene'] ?? '') !== 'waybill' || !isset($data['config'], $data['payload'])) throw new CommonException('原顺丰面单账号快照不可用，请管理员核实，未切换当前账号');
        return $data;
    }

    private static function encode(array $data): string { return Cipher::encrypt(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)); }
    private static function goodsIds($ids): array
    {
        if (!is_array($ids)) $ids = explode(',', (string)$ids);
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids || min($ids) <= 0) return [];
        sort($ids, SORT_NUMERIC);
        return $ids;
    }
    private static function safeReason(string $message, array $snapshot): string
    {
        foreach (['check_word', 'client_code', 'monthly_card'] as $field) {
            $secret = (string)($snapshot['config'][$field] ?? '');
            if ($secret !== '') $message = str_replace($secret, '[已隐藏]', $message);
        }
        foreach (['token', 'url'] as $field) {
            $secret = (string)($snapshot['pdf'][$field] ?? '');
            if ($secret !== '') $message = str_replace($secret, '[已隐藏]', $message);
        }
        $message = preg_replace('~https?://[^\s<>]+~i', '[已隐藏链接]', $message);
        $message = preg_replace('/[\x00-\x1f\x7f]/', ' ', (string)$message);
        return mb_substr(trim((string)$message) ?: '服务商暂未返回可确认说明', 0, 220);
    }
    private static function lockKey(array $data): string
    {
        return $data['business_type'] . ':' . (!empty($data['business_order_id']) ? 'order:' . $data['business_order_id'] : 'package:' . $data['business_id']);
    }
}
