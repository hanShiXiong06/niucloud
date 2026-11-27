<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\address\contract;

interface AddressParseProviderInterface
{
    public function key(): string;

    public function name(): string;

    public function healthCheck(int $siteId): bool;

    public function parse(int $siteId, string $address): array;
}
