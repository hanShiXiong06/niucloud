<?php
declare(strict_types=1);
namespace addon\hsx_express\app\service\core;

use addon\hsx_express\app\support\OperationLock;
use core\exception\CommonException;

/** 持久化任务决定原渠道；当前配置只决定没有有效任务的新申请。 */
final class WaybillTaskDispatcher
{
    private TaskRepository $repository;
    private $factory;
    private $lockRunner;

    public function __construct(?TaskRepository $repository = null, ?callable $factory = null, ?callable $lockRunner = null)
    {
        $this->repository = $repository ?? new TaskRepository();
        $this->factory = $factory ?? static function (string $provider) {
            if ($provider === 'kuaidi100') return new LogisticsService();
            if ($provider === 'sf_direct') return new SfWaybillService();
            throw new CommonException('原物流渠道未接入，系统不会使用其他渠道处理此任务');
        };
        $this->lockRunner = $lockRunner ?? static fn($site, $key, $fn) => OperationLock::run($site, $key, $fn);
    }

    public function execute(int $siteId, string $operation, array $payload, string $selectedProvider = ''): array
    {
        if ($siteId <= 0) throw new CommonException('物流任务必须属于具体站点');
        if ($operation === 'create') {
            $type = (string)($payload['business_type'] ?? '');
            $order = (int)($payload['order_id'] ?? 0);
            $key = $type . ':' . ($order > 0 ? 'order:' . $order : 'package:' . (string)($payload['business_id'] ?? ''));
            return ($this->lockRunner)($siteId, $key, function () use ($siteId, $operation, $payload, $selectedProvider) {
                $existing = $this->find($siteId, $payload);
                if ($existing && !in_array($existing['state'], ['failed', 'cancelled'], true)) {
                    $view = self::view($existing);
                    $view['reused'] = true;
                    $view['message'] = '本包裹已有物流任务，继续使用原渠道、原账号及原地址；请勿重复取号。';
                    return $view;
                }
                $service = ($this->factory)($selectedProvider);
                return $service->execute($siteId, $operation, $payload);
            });
        }
        $task = $this->find($siteId, $payload);
        if (!$task) {
            if ($operation === 'query') return [];
            throw new CommonException('任务不存在或不属于本站');
        }
        if ($operation === 'query') return self::view($task); // 只读本地，不向服务商重发。
        $service = ($this->factory)(self::provider($task));
        return $service->execute($siteId, $operation, ['task_id' => (int)$task['id']] + $payload);
    }

    public function downloadPdf(int $siteId, int $id): array
    {
        $task = $this->repository->find($siteId, $id);
        if (!$task || self::provider($task) !== 'sf_direct') throw new CommonException('本站任务没有可下载的顺丰 PDF');
        return ($this->factory)('sf_direct')->downloadPdf($siteId, $id);
    }

    public static function view(array $task): array
    {
        $provider = self::provider($task);
        if ($provider === 'sf_direct') return SfWaybillService::view($task);
        if ($provider === 'kuaidi100') return LogisticsService::view($task) + ['provider' => 'kuaidi100', 'provider_key' => 'hsx_express_kuaidi100'];
        throw new CommonException('原物流渠道未接入，不能将旧任务当成其他渠道处理');
    }

    private function find(int $siteId, array $payload): array
    {
        return !empty($payload['task_id']) ? $this->repository->find($siteId, (int)$payload['task_id'])
            : $this->repository->findBusiness($siteId, (string)($payload['business_type'] ?? ''), (string)($payload['business_id'] ?? ''));
    }

    private static function provider(array $task): string
    {
        return (string)($task['provider'] ?? 'kuaidi100');
    }
}
