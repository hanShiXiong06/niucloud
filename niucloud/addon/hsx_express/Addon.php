<?php
declare(strict_types=1);
namespace addon\hsx_express;

use app\service\core\addon\CoreAddonInstallService;

final class Addon
{
    public function install(): bool { return CoreAddonInstallService::executeSql(__DIR__ . '/sql/install.sql'); }
    public function upgrade(): bool { return $this->install(); }
    // 运单和原账户快照是业务凭证，卸载不销毁，重装后仍可查询。
    public function uninstall(): bool { return true; }
}
