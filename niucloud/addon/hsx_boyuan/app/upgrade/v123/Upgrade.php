<?php
namespace addon\hsx_boyuan\app\upgrade\v123;

final class Upgrade
{
    public function handle(): void
    {
        (new \app\service\core\menu\CoreMenuService())->refreshAddonMenu('hsx_boyuan');
    }
}
