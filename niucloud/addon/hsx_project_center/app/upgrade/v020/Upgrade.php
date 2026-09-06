<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\upgrade\v020;

use addon\hsx_project_center\app\support\ProjectCenterSchema;

/**
 * 0.2.0 升级入口。
 *
 * 框架的一键升级只会执行 app/upgrade/v{version}/Upgrade.php，
 * 不会调用插件根目录 Addon::upgrade()，因此在这里统一执行幂等迁移。
 */
final class Upgrade
{
    public function handle(): bool
    {
        ProjectCenterSchema::migrate();
        return true;
    }
}
