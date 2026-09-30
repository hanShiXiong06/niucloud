<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\core\delivery\electronic_sheet;

use core\exception\CommonException;

/** Registers capabilities only. Execution always targets exactly one selected provider. */
class ElectronicSheetProviderRegistry
{
    public function all(int $siteId): array
    {
        $providers = [];
        foreach ((array) event('PhoneShopElectronicSheetProviders', ['site_id' => $siteId]) as $response) {
            if (!is_array($response)) continue;
            foreach (($response['providers'] ?? []) as $provider) {
                if (!is_array($provider)) continue;
                $key = (string) ($provider['key'] ?? '');
                $handler = (string) ($provider['handler'] ?? '');
                if ($key === '' || $key === 'kdbird' || !is_callable([$handler, 'execute'])) continue;
                if (isset($providers[$key])) throw new CommonException('电子面单服务商标识重复，请检查已安装物流插件');
                $providers[$key] = $provider;
            }
        }
        return $providers;
    }

    public function options(int $siteId): array
    {
        $options = [['key' => 'kdbird', 'label' => '快递鸟（原有方式）', 'external' => false]];
        foreach ($this->all($siteId) as $provider) {
            unset($provider['handler']);
            $provider['external'] = true;
            $options[] = $provider;
        }
        return $options;
    }

    public function execute(int $siteId, string $providerKey, string $operation, array $payload): array
    {
        $provider = $this->all($siteId)[$providerKey] ?? null;
        if (!$provider) throw new CommonException('原电子面单服务商未安装或不可用，请恢复原服务商后处理；系统不会自动改用其他服务商');
        return ($provider['handler'])::execute($siteId, $operation, $payload);
    }

    /** Shares the provider's order mutex with cancel/create; held through the shipment transaction. */
    public function withBusinessLock(int $siteId, string $providerKey, int $orderId, callable $operation)
    {
        $provider = $this->all($siteId)[$providerKey] ?? null;
        if (!$provider || !is_callable([$provider['handler'], 'withBusinessLock'])) throw new CommonException('物流服务不支持安全交件确认，请更新对应插件后重试');
        return ($provider['handler'])::withBusinessLock($siteId, 'phone_shop', $orderId, $operation);
    }

    /** Manual delivery also participates, including after changing the selected provider. */
    public function withOrderLocks(int $siteId, int $orderId, callable $operation, string $requiredProvider = '')
    {
        $providers = $this->all($siteId);
        if ($requiredProvider !== '' && (!isset($providers[$requiredProvider]) || !is_callable([$providers[$requiredProvider]['handler'], 'withBusinessLock']))) {
            throw new CommonException('原物流服务不可用，不能核验安全交件；请恢复对应插件后重试');
        }
        // Deterministic acquisition order permits several optional providers without lock inversion.
        ksort($providers, SORT_STRING);
        foreach (array_reverse($providers, true) as $provider) {
            $handler = $provider['handler'];
            if (!is_callable([$handler, 'withBusinessLock'])) continue;
            $next = $operation;
            $operation = static fn() => $handler::withBusinessLock($siteId, 'phone_shop', $orderId, $next);
        }
        return $operation();
    }
}
