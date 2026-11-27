<?php
namespace addon\hsx_boyuan\app\upgrade\v120;
final class Upgrade
{
    public function handle(): void
    {
        // No new schema and no deletion of historical logs or business records.
        (new \app\service\core\menu\CoreMenuService())->refreshAddonMenu('hsx_boyuan');
    }
}
