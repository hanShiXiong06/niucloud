<?php
namespace addon\hsx_boyuan\app\upgrade\v122;
final class Upgrade
{
    public function handle(): void
    {
        // Menu refresh only. Installing/upgrading must not create a sale or start posting old orders.
        (new \app\service\core\menu\CoreMenuService())->refreshAddonMenu('hsx_boyuan');
    }
}
