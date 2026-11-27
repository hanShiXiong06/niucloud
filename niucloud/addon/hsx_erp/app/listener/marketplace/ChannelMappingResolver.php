<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener\marketplace;

use addon\hsx_erp\app\service\admin\ErpChannelMappingService;

/** 供渠道按契约读取映射结果，不暴露 ERP 私有表。 */
final class ChannelMappingResolver
{
    public function handle(array $event = []): array
    {
        return (new ErpChannelMappingService())->resolve($event);
    }
}
