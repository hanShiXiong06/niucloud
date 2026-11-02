<?php
declare(strict_types=1);
/** Plugin-only additive schema and standard menu refresh. No owner backfill. */
if (PHP_SAPI!=='cli' || !in_array('--apply-plugin-schema',$argv??[],true)) {
    fwrite(STDERR,"Requires --apply-plugin-schema. Back up hsx_lead tables first.\n"); exit(2);
}
$root=dirname(__DIR__,3);
require_once $root.'/vendor/autoload.php';
try {
    $app=new think\App($root); $app->initialize();
    $report=addon\hsx_lead\app\support\Schema::upgrade();
    (new app\service\core\menu\CoreMenuService())->refreshAddonMenu('hsx_lead');
    echo json_encode($report+['menu_refreshed'=>true,'role_authorizations_changed'=>false,
        'ownership_note'=>'历史归属保持0，仅全量管理员可见；请管理员明确转交，不会自动猜测原录单人。',
        'permission_note'=>'请按岗位授予全量数据与转交归属权限，普通员工仅访问本人归属。'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
} catch(Throwable $error) {
    $message=$error instanceof PDOException?'数据库升级未完成，请核对DDL权限及锁等待':$error->getMessage();
    fwrite(STDERR,'升级未完成：'.$message.'；MySQL DDL可能已分步提交，可排除原因后重试。未回填归属或删除业务数据。'."\n"); exit(1);
}
