<?php
declare(strict_types=1);

namespace addon\hsx_phone_query\app\service\core\provider;

use core\base\BaseCoreService;

/**
 * 查机领域网关：业务层只面向此服务，不感知服务商协议差异。
 */
class PhoneQueryGatewayService extends BaseCoreService
{
    private PhoneQueryProviderRegistry $registry;

    public function __construct(?PhoneQueryProviderRegistry $registry = null)
    {
        parent::__construct();
        $this->registry = $registry ?? new PhoneQueryProviderRegistry();
    }

    public function query(array $channel, array $mapping, string $queryCode): array
    {
        $provider = (string)($channel['provider'] ?? '');
        return $this->registry->resolve($provider)->query($channel, $mapping, $queryCode);
    }
}
