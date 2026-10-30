<?php
declare(strict_types=1);
/**
 * After backing up plugin tables and copying 1.1.0 files:
 * php niucloud/addon/hsx_lead/scripts/upgrade_1_1_0.php --apply-plugin-schema
 * Executes only plugin table ALTERs plus normal plugin notice/schedule registration.
 */
if (PHP_SAPI!=='cli' || !in_array('--apply-plugin-schema',$argv??[],true)) {
    fwrite(STDERR,"Explicit --apply-plugin-schema is required. Back up hsx_lead tables first.\n"); exit(2);
}
$root=dirname(__DIR__,3);
require_once $root.'/vendor/autoload.php';
try {
    $app=new think\App($root); $app->initialize();
    $report=addon\hsx_lead\app\support\Schema::upgrade();
    (new addon\hsx_lead\Addon())->upgrade();
    (new app\service\core\menu\CoreMenuService())->refreshAddonMenu('hsx_lead');
    echo json_encode($report+['plugin_configuration_synced'=>true,'menu_refreshed'=>true,'role_authorizations_changed'=>false,
        'permission_note'=>'请管理员为需要操作通知配置、连接探测等新功能的角色补授本插件新权限。'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
} catch(Throwable $error) {
    $message=$error instanceof PDOException?'数据库升级执行失败，请检查DDL权限、锁等待或索引冲突':$error->getMessage();
    fwrite(STDERR,'升级未完成：'.$message."。未删除业务记录；MySQL DDL可能已分步提交，排除原因后可重试。\n"); exit(1);
}

