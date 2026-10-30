<?php
namespace addon\hsx_boyuan\app\upgrade\v110;
final class Upgrade
{
    public function handle(): void
    {
        (new \addon\hsx_boyuan\Addon())->upgrade();
        (new \app\service\core\menu\CoreMenuService())->refreshAddonMenu('hsx_boyuan');
    }
}
