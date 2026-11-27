<?php
namespace addon\hsx_boyuan\app\upgrade\v130;

final class Upgrade
{
    public function handle():void
    {
        \addon\hsx_boyuan\app\support\CatalogUpgrade::run();
        (new \app\service\core\menu\CoreMenuService())->refreshAddonMenu('hsx_boyuan');
    }
}
