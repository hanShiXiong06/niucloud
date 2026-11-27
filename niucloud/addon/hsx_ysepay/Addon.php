<?php
declare(strict_types=1);

namespace addon\hsx_ysepay;

class Addon
{
    public function install(): bool
    {
        return true;
    }

    public function uninstall(): bool
    {
        return true;
    }

    public function upgrade(): bool
    {
        return true;
    }
}
