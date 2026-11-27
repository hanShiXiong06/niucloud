<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\upgrade\v110;

final class Upgrade
{
    public function handle(): void
    {
        (new \addon\hsx_erp_integration\Addon())->upgrade();
        (new \app\service\core\menu\CoreMenuService())->refreshAddonMenu('hsx_erp_integration');
    }
}
