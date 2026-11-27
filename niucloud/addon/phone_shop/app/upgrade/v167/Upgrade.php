<?php
declare(strict_types=1);
namespace addon\phone_shop\app\upgrade\v167;

use addon\phone_shop\app\service\core\upgrade\GoodsDetailIndexes;

final class Upgrade
{
    public function handle(): void
    {
        (new GoodsDetailIndexes())->handle();
    }
}
