<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\express;

/**
 * 快递能力门面：只负责编排统一契约，不感知任何厂商 key/header/签名。
 */
class ExpressGatewayService
{
    private $registry;

    public function __construct(?ExpressProviderRegistry $registry = null)
    {
        $this->registry = $registry ?: new ExpressProviderRegistry();
    }

    public function activeProvider(int $siteId): array
    {
        $provider = $this->registry->resolve($siteId);
        return ['key' => $provider->key(), 'name' => $provider->name()];
    }

    public function products(int $siteId): array
    {
        return $this->registry->resolve($siteId)->products($siteId);
    }

    public function isReady(int $siteId): bool
    {
        try {
            return $this->registry->resolve($siteId)->healthCheck($siteId);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function quote(int $siteId, array $request): array
    {
        return $this->registry->resolve($siteId, $this->providerKey($request))->quote($siteId, $request);
    }

    public function create(int $siteId, array $request): array
    {
        return $this->registry->resolve($siteId, $this->providerKey($request))->create($siteId, $request);
    }

    public function cancel(int $siteId, array $request): array
    {
        return $this->registry->resolve($siteId, $this->providerKey($request))->cancel($siteId, $request);
    }

    public function modify(int $siteId, array $request): array
    {
        return $this->registry->resolve($siteId, $this->providerKey($request))->modify($siteId, $request);
    }

    public function detail(int $siteId, array $request): array
    {
        return $this->registry->resolve($siteId, $this->providerKey($request))->detail($siteId, $request);
    }

    public function waybill(int $siteId, array $request): array
    {
        return $this->registry->resolve($siteId, $this->providerKey($request))->waybill($siteId, $request);
    }

    public function account(int $siteId): array
    {
        return $this->registry->resolve($siteId)->account($siteId);
    }

    private function providerKey(array $request): string
    {
        return trim((string)($request['provider'] ?? $request['provider_key'] ?? ''));
    }
}
