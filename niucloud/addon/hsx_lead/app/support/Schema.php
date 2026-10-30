<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\support;

use think\facade\Db;

/** Idempotent additive migration of plugin tables only. No row backfill or framework ALTER. */
final class Schema
{
    public static function upgrade(): array
    {
        $prefix=(string)Db::connect()->getConfig('prefix');
        if (!preg_match('/^[a-zA-Z0-9_]*$/D',$prefix)) throw new \RuntimeException('Unsupported table prefix');
        $changes=[];
        $definitions=[
            'task'=>[
                'deal_items_json'=>'LONGTEXT NULL','confirmed_items_json'=>'LONGTEXT NULL','deal_history_json'=>'LONGTEXT NULL',
                'deal_revision'=>'INT UNSIGNED NOT NULL DEFAULT 0','last_verified_revision'=>'INT UNSIGNED NOT NULL DEFAULT 0',
                'completion_status'=>"VARCHAR(24) NOT NULL DEFAULT 'none'",'close_remaining'=>'TINYINT UNSIGNED NOT NULL DEFAULT 0',
                'remaining_reason'=>"VARCHAR(1000) NOT NULL DEFAULT ''",
            ],
            'commission'=>['deal_revision'=>'INT UNSIGNED NOT NULL DEFAULT 0'],
            'audit'=>['dedupe_key'=>'CHAR(64) DEFAULT NULL'],
        ];
        foreach($definitions as $short=>$fields) {
            $table=$prefix.'hsx_lead_'.$short;
            $columns=array_column(Db::query('SHOW COLUMNS FROM `'.$table.'`'),null,'Field');
            foreach($fields as $field=>$definition) if (!isset($columns[$field])) {
                Db::execute('ALTER TABLE `'.$table.'` ADD COLUMN `'.$field.'` '.$definition);
                $changes[]='hsx_lead_'.$short.'.'.$field;
            }
        }
        $table=$prefix.'hsx_lead_commission'; $indexes=self::indexes($table);
        if (!isset($indexes['uk_site_task_revision'])) {
            Db::execute('ALTER TABLE `'.$table.'` ADD UNIQUE KEY `uk_site_task_revision` (`site_id`,`task_id`,`deal_revision`)');
            $changes[]='hsx_lead_commission.uk_site_task_revision';
        } elseif (!$indexes['uk_site_task_revision']['unique'] || $indexes['uk_site_task_revision']['columns']!==['site_id','task_id','deal_revision']) {
            throw new \RuntimeException('Existing commission revision index conflicts; inspect before retrying');
        }
        // Build the new constraint first. Never remove unrelated custom indexes.
        foreach($indexes as $name=>$index) if ($index['unique'] && $index['columns']===['site_id','task_id']) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/D',$name)) throw new \RuntimeException('Unsupported index name');
            Db::execute('ALTER TABLE `'.$table.'` DROP INDEX `'.$name.'`');
            $changes[]='hsx_lead_commission.removed_'.$name;
        }
        $audit=$prefix.'hsx_lead_audit'; $indexes=self::indexes($audit);
        if (!isset($indexes['uk_site_dedupe'])) {
            Db::execute('ALTER TABLE `'.$audit.'` ADD UNIQUE KEY `uk_site_dedupe` (`site_id`,`dedupe_key`)');
            $changes[]='hsx_lead_audit.uk_site_dedupe';
        } elseif (!$indexes['uk_site_dedupe']['unique'] || $indexes['uk_site_dedupe']['columns']!==['site_id','dedupe_key']) {
            throw new \RuntimeException('Existing audit dedupe index conflicts; inspect before retrying');
        }
        foreach(['task','commission','audit'] as $short) Db::connect()->getSchemaInfo($prefix.'hsx_lead_'.$short,true);
        return ['version'=>'1.1.0','changes'=>$changes,'business_rows_rewritten'=>0,'framework_tables_altered'=>0,
            'notice'=>'MySQL DDL may auto-commit; retries are idempotent. Back up plugin tables before upgrading.'];
    }

    private static function indexes(string $table): array
    {
        $result=[];
        foreach(Db::query('SHOW INDEX FROM `'.$table.'`') as $row) {
            $name=$row['Key_name']; $result[$name]['unique']=(int)$row['Non_unique']===0;
            $result[$name]['columns'][(int)$row['Seq_in_index']]=$row['Column_name'];
        }
        foreach($result as &$index) { ksort($index['columns']); $index['columns']=array_values($index['columns']); }
        unset($index);
        return $result;
    }
}

