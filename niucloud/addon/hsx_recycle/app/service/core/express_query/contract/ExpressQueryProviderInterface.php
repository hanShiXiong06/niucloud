<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\express_query\contract;

interface ExpressQueryProviderInterface
{
    public function key(): string;

    public function name(): string;

    public function healthCheck(int $siteId): bool;

    public function query(int $siteId, string $expressNo, string $mobile = ''): array;
}
