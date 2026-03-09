<?php

namespace addon\sd_xiaoyuan;

use think\facade\Db;

class Addon
{
    public function install()
    {
        return true;
    }

    public function uninstall()
    {
        $tables = [
            'xiaoyuan_campus',
            'xiaoyuan_runner',
            'xiaoyuan_order',
            'xiaoyuan_order_log',
            'xiaoyuan_evaluate',
            'xiaoyuan_address',
            'xiaoyuan_coupon',
            'xiaoyuan_coupon_record',
            'xiaoyuan_vip',
            'xiaoyuan_runner_balance_log',
            'xiaoyuan_withdraw',
            'xiaoyuan_appeal',
            'xiaoyuan_config'
        ];
        
        $prefix = config('database.connections.mysql.prefix');
        
        foreach ($tables as $table) {
            $tableName = $prefix . $table;
            Db::execute("DROP TABLE IF EXISTS `{$tableName}`");
        }
        
        return true;
    }

    public function upgrade()
    {
        return true;
    }
}
