<?php
declare(strict_types=1);

namespace addon\hsx_project_center;

use addon\hsx_project_center\app\support\ProjectCenterSchema;

final class Addon
{
    public function install(): bool
    {
        ProjectCenterSchema::migrate();
        return true;
    }

    public function uninstall(): bool { return true; }

    public function upgrade(): bool
    {
        ProjectCenterSchema::migrate();
        return true;
    }
}
