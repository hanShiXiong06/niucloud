<?php
namespace addon\hsx_boyuan\app\upgrade\v121;
final class Upgrade
{
    public function handle(): void
    {
        // Opening posts only after an authorized user previews and confirms. No SQL on upgrade.
        (new \app\service\core\menu\CoreMenuService())->refreshAddonMenu('hsx_boyuan');
    }
}
