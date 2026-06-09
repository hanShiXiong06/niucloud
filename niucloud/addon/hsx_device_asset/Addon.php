<?php

namespace addon\hsx_device_asset;

use think\facade\Db;

/**
 * 设备资产中台插件
 */
class Addon
{
    public function install()
    {
        $this->installTables();
        return true;
    }

    public function uninstall()
    {
        return true;
    }

    public function upgrade()
    {
        $this->installTables();
        return true;
    }

    private function installTables(): void
    {
        $sqlFile = __DIR__ . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'install.sql';
        if (!is_file($sqlFile)) {
            return;
        }

        $prefix = config('database.connections.mysql.prefix');
        $sql = str_replace('{{prefix}}', $prefix, (string)file_get_contents($sqlFile));
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            Db::execute($statement);
        }
    }
}
