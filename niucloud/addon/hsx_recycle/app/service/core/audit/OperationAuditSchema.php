<?php
declare(strict_types=1);
namespace addon\hsx_recycle\app\service\core\audit;

use think\facade\Db;

/** Existing order log, additive only. No DDL inside a request, worker or business transaction. */
final class OperationAuditSchema
{
    public static function changes(): array
    {
        $todo=[];
        foreach (['recycle_order_log','recycle_device_log'] as $name) {
            $table=Db::name($name)->getTable();
            if (!preg_match('/^[a-zA-Z0-9_]+$/D',$table)) throw new \RuntimeException('日志表前缀无效');
            $ops=[];
            $columns=array_column(Db::query("SHOW COLUMNS FROM `$table`"),'Field');
            $keys=array_column(Db::query("SHOW INDEX FROM `$table`"),'Key_name');
            if ($name==='recycle_order_log') {
                if (!in_array('audit_key',$columns,true)) $ops[]="ADD COLUMN audit_key char(64) CHARACTER SET ascii COLLATE ascii_bin NULL COMMENT '内部消息去重标识'";
                if (!in_array('audit_data',$columns,true)) $ops[]="ADD COLUMN audit_data text NULL COMMENT '内部系统操作摘要（非接口原文）'";
                if (!in_array('uk_order_audit',$keys,true)) $ops[]='ADD UNIQUE INDEX uk_order_audit(site_id,order_id,audit_key)';
            }
            if (!in_array('idx_timeline_site',$keys,true)) $ops[]='ADD INDEX idx_timeline_site(site_id,create_at,id)';
            if (!in_array('idx_timeline_order',$keys,true)) $ops[]='ADD INDEX idx_timeline_order(order_id,create_at,id)';
            if ($ops) $todo[]="ALTER TABLE `$table` ".implode(', ',$ops);
        }
        return $todo;
    }
    public static function apply(): void {foreach (self::changes() as $sql) Db::execute($sql);}
}
