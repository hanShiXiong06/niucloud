<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\address;

use core\exception\CommonException;

class AddressParseGatewayService
{
    private AddressParseProviderRegistry $registry;

    public function __construct(?AddressParseProviderRegistry $registry = null)
    {
        $this->registry = $registry ?: new AddressParseProviderRegistry();
    }

    public function parse(int $siteId, string $address, string $providerKey = ''): array
    {
        $address = trim($address);
        if ($address === '') {
            throw new CommonException('地址内容不能为空');
        }
        return $this->registry->resolve($siteId, trim($providerKey))->parse($siteId, $address);
    }
}
