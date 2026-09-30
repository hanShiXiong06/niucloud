<?php
declare(strict_types=1);
namespace addon\hsx_express\app\service\core;

use addon\hsx_express\app\support\Cipher;
use addon\hsx_express\app\support\OperationLock;
use core\exception\CommonException;

/** 面单任务编排。地址、账号只加密存储；未知结果不能重下，取消后新建独立尝试保留历史。 */
class LogisticsService
{
    private Kuaidi100Client $client;
    private TaskRepository $repository;
    private $configLoader;
    private $lockRunner;
    private $operationGuard;
    public function __construct(?Kuaidi100Client $client = null, ?TaskRepository $repository = null, ?callable $configLoader = null, ?callable $lockRunner = null, ?callable $operationGuard = null)
    {
        $this->client = $client ?? new Kuaidi100Client();
        $this->repository = $repository ?? new TaskRepository();
        $this->configLoader = $configLoader ?? static fn($siteId) => (new ConfigService())->get($siteId);
        $this->lockRunner = $lockRunner ?? static fn($siteId, $key, $fn) => OperationLock::run($siteId, $key, $fn);
        $this->operationGuard = $operationGuard ?? static fn($data) => event('HsxExpressTaskOperationGuard', $data);
    }

    public function execute(int $siteId, string $operation, array $payload): array
    {
        if ($siteId <= 0) throw new CommonException('物流操作必须归属具体站点');
        if ($operation === 'create') return $this->create($siteId, $payload);
        if ($operation === 'device_status') {
            $config = ($this->configLoader)($siteId);
            if (empty($config['device_id'])) throw new CommonException('尚未设置云打印设备码');
            $result = $this->client->request(Kuaidi100Client::DEVICE_URL, 'devstatus', ['siid' => $config['device_id']], $config);
            return ['success' => ($result['result'] ?? false) === true && (string)($result['returnCode'] ?? '') === '200',
                'message' => WaybillProtocol::safeMessage((string)($result['message'] ?? '设备状态已返回'), $config), 'data' => $result['data'] ?? [], 'verified_scope' => 'printer_only'];
        }
        $task = !empty($payload['task_id']) ? $this->repository->find($siteId, (int)$payload['task_id'])
            : $this->repository->findBusiness($siteId, (string)($payload['business_type'] ?? ''), (string)($payload['business_id'] ?? ''));
        if ($operation === 'query') return $task ? self::view($task) : [];
        if (!$task) throw new CommonException('物流任务不存在或不属于本站');
        if (!in_array($operation, ['reprint', 'cancel', 'recover'], true)) throw new CommonException('不支持的物流操作');
        return ($this->lockRunner)($siteId, self::lockKey($task), function () use ($task, $siteId, $operation, $payload) {
            $fresh = $this->repository->find($siteId, (int)$task['id']);
            if ($operation === 'recover') return $this->recover($fresh, $payload);
            return $operation === 'reprint' ? $this->reprint($fresh, $payload) : $this->cancel($fresh, $payload);
        });
    }

    private function create(int $siteId, array $payload): array
    {
        $payload = WaybillProtocol::normalizePayload($payload);
        return ($this->lockRunner)($siteId, self::lockKey($payload + ['business_order_id' => $payload['order_id']]), function () use ($siteId, $payload) {
            $existing = $this->repository->findBusiness($siteId, $payload['business_type'], $payload['business_id']);
            if ($existing && !in_array($existing['state'], ['failed', 'cancelled'], true)) {
                $view = self::view($existing); $view['reused'] = true;
                if ($existing['payload_hash'] !== hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))) {
                    $view['message'] = '该包裹已有物流任务，仍使用原地址和账号；如要修改，请先核实并取消原运单。';
                }
                return $view;
            }
            if ($payload['order_id'] > 0 && $payload['order_goods_ids']) {
                foreach ($this->repository->activeForOrder($siteId, $payload['business_type'], $payload['order_id']) as $active) {
                    $refs = json_decode((string)$active['business_refs_json'], true) ?: [];
                    if (array_intersect($payload['order_goods_ids'], $refs['order_goods_ids'] ?? [])) throw new CommonException('选中的商品已有未取消物流任务 ' . $active['task_no'] . '，请使用原运单，勿拆换包裹重复取号');
                }
            }
            $config = ($this->configLoader)($siteId);
            if (empty($config['enabled'])) throw new CommonException('本站尚未启用物流服务，请先完成物流配置');
            $check = ConfigService::readiness($config);
            if (!$check['ready']) throw new CommonException('物流配置不完整：' . implode('；', array_column(array_filter($check['checks'], static fn($row) => !$row['passed']), 'message')));
            $taskNo = 'HX' . date('YmdHis') . bin2hex(random_bytes(10));
            $snapshot = ['config' => $config, 'payload' => $payload, 'salt' => bin2hex(random_bytes(24))];
            $param = WaybillProtocol::create($config, $payload, $taskNo, $snapshot['salt'], $siteId);
            $task = $this->repository->create(['site_id' => $siteId, 'task_no' => $taskNo,
                'business_type' => $payload['business_type'], 'business_id' => $payload['business_id'], 'attempt' => (int)($existing['attempt'] ?? 0) + 1,
                'business_order_id' => $payload['order_id'], 'business_refs_json' => json_encode(['order_id' => $payload['order_id'], 'order_goods_ids' => $payload['order_goods_ids'], 'order_no' => $payload['business_no']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'payload_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), 'provider' => 'kuaidi100',
                'carrier' => $config['carrier'], 'exp_type' => $config['exp_type'], 'print_type' => $param['printType'], 'state' => 'creating',
                'snapshot_cipher' => Cipher::encrypt(json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                'logs_json' => '[]', 'create_at' => time(), 'update_at' => time(), 'submitted_at' => time(),
                'message' => '已保存取号任务，正在请求快递100；请勿重复操作']);
            try { $result = $this->client->request(Kuaidi100Client::WAYBILL_URL, 'order', $param, $config); }
            catch (ProviderException $e) {
                return self::view($this->repository->update($task, ['state' => $e->isUnknown() ? 'unknown' : 'failed', 'message' => $e->getMessage()], 'create'));
            }
            $data = is_array($result['data'] ?? null) ? $result['data'] : [];
            $complete = !empty($data['taskId']) && !empty($data['kuaidinum']);
            $state = $complete ? ($param['printType'] === 'CLOUD' ? (WaybillProtocol::resultSucceeded($result) ? 'print_pending' : 'print_failed') : 'ready')
                : (WaybillProtocol::safeCreateFailure($result) ? 'failed' : 'unknown');
            $message = $complete ? ($state === 'print_pending' ? '已取号，等待云打印结果；尚未确认交件。' : ($state === 'ready' ? '已取号，可打开面单打印；请实际交件后确认发货。' : '已取号，但打印未成功，请检查设备后补打原单。'))
                : ($state === 'failed' ? '未取号：' : '结果待核实，请勿重复下单：') . WaybillProtocol::safeMessage((string)($result['message'] ?? '响应缺少完整运单标识'), $config, $payload);
            return self::view($this->repository->update($task, ['state' => $state, 'message' => $message,
                'provider_task_id' => mb_substr((string)($data['taskId'] ?? ''), 0, 100), 'waybill_no' => mb_substr((string)($data['kuaidinum'] ?? ''), 0, 100),
                'provider_order_no' => mb_substr((string)($data['kdComOrderNum'] ?? ''), 0, 100),
                'label' => implode(',', WaybillProtocol::labels((string)($data['label'] ?? ''))), 'last_code' => (string)($result['code'] ?? '')], 'create'));
        });
    }

    private function reprint(array $task, array $payload): array
    {
        if (!self::view($task)['can_reprint']) throw new CommonException('当前不可补打：需已成功取号、结果明确，且未取消、在下单2天内、补打少于10次；请先刷新核实原任务');
        if ($task['state'] === 'print_pending' && (int)($payload['confirm'] ?? 0) !== 1) throw new CommonException('打印结果未确认，请先检查实体设备是否已经出纸；确认补打可能重复出纸后再继续');
        $snapshot = self::snapshot($task); $config = $snapshot['config'];
        // 先记录尝试：即使请求超时，不能因此无限重试、重复出纸。
        $task = $this->repository->update($task, ['reprint_count' => (int)($task['reprint_count'] ?? 0) + 1, 'state' => 'print_pending', 'message' => '正在补打原运单，不会重新取号'], 'reprint_requested');
        try { $result = $this->client->request(Kuaidi100Client::WAYBILL_URL, 'printOld', ['taskId' => $task['provider_task_id']], $config); }
        catch (ProviderException $e) { return self::view($this->repository->update($task, ['message' => '补打结果待核实，请检查打印机或快递100后台，勿连续补打'], 'reprint_unknown')); }
        $success = WaybillProtocol::resultSucceeded($result);
        $changes = ['state' => $success ? ($task['print_type'] === 'CLOUD' ? 'print_pending' : 'ready') : 'print_failed',
            'last_code' => (string)($result['code'] ?? ''), 'message' => $success ? ($task['print_type'] === 'CLOUD' ? '补打已提交，等待打印回调' : '原单面单已重新生成，可打开面单打印') : '原单补打失败：' . WaybillProtocol::safeMessage((string)($result['message'] ?? ''), $config, $snapshot['payload'])];
        $label = is_string($result['data'] ?? null) ? $result['data'] : (string)($result['data']['label'] ?? '');
        if ($success && WaybillProtocol::labels($label)) $changes['label'] = implode(',', WaybillProtocol::labels($label));
        return self::view($this->repository->update($task, $changes, 'reprint'));
    }

    /** 不是只读查询：原请求未受理时可能首次创建运单，必须由操作人显式确认。 */
    private function recover(array $task, array $payload): array
    {
        if ((int)($payload['confirm'] ?? 0) !== 1) throw new CommonException('请先确认：按原单号恢复结果可能首次实际取号并计费，不是只读查询');
        if (!self::view($task)['can_recover']) throw new CommonException('只能恢复47小时内结果待核实的取号任务；其他情况请联系服务商核实，勿重复下单');
        $current = ($this->configLoader)((int)$task['site_id']);
        if (empty($current['enabled'])) throw new CommonException('本站物流通道已关闭，恢复可能真实取号，请先由管理员确认是否重新开启');
        $guard = ($this->operationGuard)(['site_id' => (int)$task['site_id'], 'operation' => 'recover', 'task' => self::view($task)]);
        if (!is_array($guard) || !in_array(true, $guard, true)) throw new CommonException('无法确认原业务仍需寄件，系统未恢复请求，请检查业务插件及订单状态');
        $snapshot = self::snapshot($task); $config = $snapshot['config'];
        $param = WaybillProtocol::create($config, $snapshot['payload'], $task['task_no'], $snapshot['salt'], (int)$task['site_id']);
        $task = $this->repository->update($task, ['state' => 'creating', 'message' => '正在使用原账号、原地址和原单号恢复结果；服务商未受理原请求时可能首次取号计费'], 'recover_requested');
        try { $result = $this->client->request(Kuaidi100Client::WAYBILL_URL, 'order', $param, $config); }
        catch (ProviderException $e) { return self::view($this->repository->update($task, ['state' => 'unknown', 'message' => '原单结果仍待核实，请联系服务商；未新建本地尝试，也未切换账号'], 'recover_unknown')); }
        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        $complete = !empty($data['taskId']) && !empty($data['kuaidinum']) && in_array((string)($result['code'] ?? ''), ['200', '30011'], true);
        if (!$complete) return self::view($this->repository->update($task, ['state' => 'unknown', 'last_code' => (string)($result['code'] ?? ''),
            'message' => '未取得完整原单结果：' . WaybillProtocol::safeMessage((string)($result['message'] ?? '缺少运单标识'), $config, $snapshot['payload']) . '。请联系快递100核实，不能按新订单重下'], 'recover_unknown'));
        if ((!empty($task['provider_task_id']) && $task['provider_task_id'] !== (string)$data['taskId']) || (!empty($task['waybill_no']) && $task['waybill_no'] !== (string)$data['kuaidinum'])) {
            return self::view($this->repository->update($task, ['state' => 'unknown', 'message' => '返回的运单标识与原任务不一致，已停止自动处理，请联系快递100核实'], 'recover_conflict'));
        }
        return self::view($this->repository->update($task, ['state' => $task['print_type'] === 'CLOUD' ? 'print_pending' : 'ready',
            'provider_task_id' => (string)$data['taskId'], 'waybill_no' => (string)$data['kuaidinum'], 'provider_order_no' => (string)($data['kdComOrderNum'] ?? ''),
            'label' => implode(',', WaybillProtocol::labels((string)($data['label'] ?? ''))), 'last_code' => (string)$result['code'],
            'message' => (string)$result['code'] === '30011' ? '已按原单号找回首次成功的运单内容；请核实打印及交件状态。' : '已按原单号取得有效运单；请核实打印与交件，费用以服务商账单为准。'], 'recover'));
    }

    private function cancel(array $task, array $payload): array
    {
        if ($task['state'] === 'cancelled') return self::view($task);
        $view = self::view($task);
        if (!$view['can_cancel']) throw new CommonException($view['cancel_unavailable_reason'] ?: '当前结果不明确或没有有效运单，不能自动取消，请先向服务商核实');
        $guard = ($this->operationGuard)(['site_id' => (int)$task['site_id'], 'operation' => 'cancel', 'task' => self::view($task)]);
        if (!is_array($guard) || !in_array(true, $guard, true)) throw new CommonException('未取得原业务的取消许可，不能核实交件状态，请检查业务插件是否已启用');
        $reason = trim((string)($payload['reason'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > 200) throw new CommonException('请填写取消原因（1至200字）；取消运单不取消业务订单或退款');
        $snapshot = self::snapshot($task); $config = $snapshot['config'];
        $task = $this->repository->update($task, ['state' => 'cancelling', 'message' => '正在取消原运单，不修改订单及财务'], 'cancel_requested');
        $original = WaybillProtocol::create($config, $snapshot['payload'], $task['task_no'], $snapshot['salt'], (int)$task['site_id']);
        $cancel = array_intersect_key($original, array_flip(['partnerId', 'partnerKey', 'partnerSecret', 'partnerName', 'code', 'net', 'checkMan']));
        $cancel += ['kuaidicom' => $task['carrier'], 'kuaidinum' => $task['waybill_no'], 'orderId' => $task['provider_order_no'] ?? '', 'expType' => $task['exp_type'], 'reason' => $reason];
        try { $result = $this->client->request(Kuaidi100Client::WAYBILL_URL, 'cancel', $cancel, $config); }
        catch (ProviderException $e) { return self::view($this->repository->update($task, ['state' => 'cancel_unknown', 'message' => '取消结果待核实，请在快递100或承运商后台确认，不能新取号'], 'cancel_unknown')); }
        $success = WaybillProtocol::resultSucceeded($result);
        // 失败可能已揽件，保留原单；不能因此新建另一个运单。
        $state = $success ? 'cancelled' : 'cancel_unknown';
        return self::view($this->repository->update($task, ['state' => $state, 'last_code' => (string)($result['code'] ?? ''),
            'message' => $success ? '运单已取消。业务订单未取消，也未发生退款；需要寄件时可重新申请。' : '取消未获确认：' . WaybillProtocol::safeMessage((string)($result['message'] ?? ''), $config, $snapshot['payload']) . '，请联系承运商核实'], 'cancel'));
    }

    public function callback(int $siteId, string $taskNo, array $input): array
    {
        $task = $this->repository->findNumber($siteId, $taskNo);
        if (!$task) throw new CommonException('任务不存在');
        return ($this->lockRunner)($siteId, self::lockKey($task), function () use ($task, $siteId, $input) {
            $task = $this->repository->find($siteId, (int)$task['id']);
            $snapshot = self::snapshot($task);
            $param = (string)($input['param'] ?? ''); $providerId = (string)($input['taskId'] ?? '');
            if (strlen($param) > 65536 || !WaybillProtocol::validCallback($param, (string)($input['sign'] ?? ''), $snapshot['salt'])) throw new CommonException('回调签名无效');
            if ($providerId === '' || (!empty($task['provider_task_id']) && $providerId !== $task['provider_task_id'])) throw new CommonException('回调任务不匹配');
            $body = json_decode($param, true);
            if (!is_array($body)) throw new CommonException('回调内容无效');
            // 未启用OCR/订阅；非打印回调不影响运单状态。验签后ACK避免无效重复推送。
            if (($input['pushType'] ?? 'printStatus') !== 'printStatus') return self::ack();
            if (!in_array((string)($body['status'] ?? ''), ['200', '201'], true)) throw new CommonException('不支持的打印状态');
            if (in_array($task['state'], ['cancelled', 'cancelling', 'cancel_unknown'], true)) return self::ack();
            $state = (string)$body['status'] === '200' ? 'printed' : 'print_failed';
            // 延迟或重复失败回调不能把已经成功打印降为失败。
            if ($task['state'] === 'printed' || $task['state'] === $state) return self::ack();
            if (empty($task['waybill_no'])) $state = 'unknown';
            $this->repository->update($task, ['state' => $state, 'provider_task_id' => $providerId, 'last_callback_at' => time(),
                'message' => $state === 'printed' ? '服务商确认打印成功；是否实际交件仍由业务人员确认。' : ($state === 'unknown' ? '收到打印回调，但原取号结果缺少运单号，请联系服务商核实' : '服务商反馈打印失败，请检查纸张、网络、设备后补打原单')], 'print_callback');
            return self::ack();
        });
    }

    public static function view(array $task): array
    {
        $names = ['creating' => '取号处理中', 'unknown' => '取号结果待核实', 'failed' => '未取号，请修正配置', 'ready' => '已取号，待打印',
            'print_pending' => '已取号，打印待确认', 'printed' => '打印成功', 'print_failed' => '已取号，打印失败',
            'cancelling' => '取消处理中', 'cancel_unknown' => '取消结果待核实', 'cancelled' => '运单已取消'];
        $state = $task['state'];
        if ($state === 'creating' && time() - (int)$task['update_at'] > 90) $state = 'unknown';
        $fields = ['id', 'site_id', 'task_no', 'business_type', 'business_id', 'attempt', 'carrier', 'exp_type', 'print_type', 'provider_task_id', 'waybill_no', 'message', 'last_code', 'reprint_count', 'create_at', 'update_at', 'submitted_at', 'last_callback_at'];
        $view = array_intersect_key($task, array_flip($fields));
        $view['task_id'] = (int)$task['id']; $view['state'] = $state; $view['state_name'] = $names[$state] ?? $state;
        $view['carrier_code'] = $task['carrier']; $view['provider_code'] = 'hsx_express_kuaidi100';
        $view['business_refs'] = json_decode((string)($task['business_refs_json'] ?? '{}'), true) ?: [];
        $view['business_no'] = (string)($view['business_refs']['order_no'] ?? '');
        $view['label'] = implode(',', WaybillProtocol::labels((string)($task['label'] ?? '')));
        $view['labels'] = WaybillProtocol::labels((string)($task['label'] ?? ''));
        $view['success'] = in_array($state, ['ready', 'print_pending', 'printed', 'print_failed', 'cancelled'], true);
        $view['can_reprint'] = (in_array($state, ['ready', 'printed', 'print_failed'], true) || ($state === 'print_pending' && time() - (int)$task['update_at'] > 90)) && !empty($task['provider_task_id'])
            && (int)($task['submitted_at'] ?? 0) >= time() - 172800 && (int)($task['reprint_count'] ?? 0) < 10;
        $carrier = WaybillCarrierCatalog::find((string)$task['carrier']);
        $view['carrier_name'] = $carrier['name'] ?? (string)$task['carrier'];
        $supportsCancel = ($carrier['capabilities']['cancel'] ?? null) === true;
        $view['cancel_unavailable_reason'] = $supportsCancel ? '' : '官方字典未标明此快递支持接口取消，请联系承运商处理，系统不会假定取消成功。';
        $view['can_cancel'] = $supportsCancel && in_array($state, ['ready', 'print_pending', 'printed', 'print_failed'], true) && !empty($task['waybill_no']);
        $view['can_recover'] = $state === 'unknown' && (int)($task['submitted_at'] ?? 0) > time() - 169200;
        $view['verification_help'] = '若取号结果待核实：47小时内可显式确认按原单号恢复（可能首次取号计费）；超过窗口或取消结果待核实，请复制任务号、服务商任务号和运单号，登录原快递100账号或联系客服核实。不要切换服务商重复取号。';
        return $view;
    }

    private static function snapshot(array $task): array
    {
        $data = json_decode(Cipher::decrypt((string)$task['snapshot_cipher']), true);
        if (!is_array($data) || !isset($data['config'], $data['payload'], $data['salt'])) throw new CommonException('原任务凭据快照不可用，请先联系管理员，未请求服务商');
        return $data;
    }
    private static function lockKey(array $data): string
    {
        return $data['business_type'] . ':' . (!empty($data['business_order_id']) ? 'order:' . $data['business_order_id'] : 'package:' . $data['business_id']);
    }
    private static function ack(): array { return ['result' => true, 'returnCode' => '200', 'message' => '提交成功']; }
}
