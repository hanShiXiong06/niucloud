<?php
declare(strict_types=1);
use addon\hsx_boyuan\app\support\{CatalogStore,CatalogUpgrade,Problem};
use think\facade\Db;
if(PHP_SAPI!=='cli' || !in_array('--isolated-local-mysql',$argv,true))exit(2);
foreach(['curl_exec','curl_multi_exec','fsockopen','stream_socket_client'] as $fn) if(function_exists($fn))exit(2);
require __DIR__.'/bootstrap.php';
$pdo=null;$schema='';$failed=false;$checks=0;
function expectMd(bool $b,string $note):void {global $checks;if(!$b)throw new RuntimeException($note);$checks++;}
try {
    [, $pdo,$schema,$site]=boyuanBoot();$prefix=Db::connect()->getConfig('prefix');$old=$prefix.'hsx_boyuan_reference';
    $pdo->exec("CREATE TABLE $old (id bigint unsigned PRIMARY KEY AUTO_INCREMENT,site_id int unsigned NOT NULL,connection_key varchar(32) NOT NULL,resource varchar(32) NOT NULL,external_id varchar(128) NOT NULL,code varchar(128) NOT NULL DEFAULT '',name varchar(255) NOT NULL,enabled tinyint unsigned NOT NULL DEFAULT 1,phone_hash varchar(64) NOT NULL DEFAULT '',phone_masked varchar(64) NOT NULL DEFAULT '',extra_json text NOT NULL,seen_run varchar(32) NOT NULL,update_at int unsigned NOT NULL DEFAULT 0,UNIQUE KEY i(site_id,connection_key,resource,external_id)) ENGINE=InnoDB");
    $insert=$pdo->prepare("INSERT INTO $old(site_id,connection_key,resource,external_id,name,extra_json,seen_run) VALUES(?,? ,?,?, '测试档案',?,'run')");
    $insert->execute([$site,'X','unknown','Z','{}']);
    try {CatalogUpgrade::run();throw new RuntimeException('unknown type not rejected');}catch(Problem $e){expectMd($e->reason==='CATALOG_UNKNOWN','unknown type blocks before renaming source');}
    $pdo->exec("DELETE FROM $old WHERE resource='unknown'");
    $insert->execute([$site,'X','brands','BAD','not json']);
    try{CatalogUpgrade::run();throw new RuntimeException('bad json accepted');}catch(Problem $e){expectMd($e->reason==='CATALOG_INVALID','invalid source remains intact before cutover');}
    $pdo->exec("DELETE FROM $old WHERE external_id='BAD'");
    foreach(CatalogStore::kinds() as $kind)$insert->execute([$site,'X',$kind,'SAME','{"status_known":true,"is_supplier":1,"is_cust":1,"name":"型号A","company_id":"001","city_name":"示范市"}']);
    $id=(int)$pdo->query("SELECT id FROM $old WHERE resource='parties'")->fetchColumn();
    $insert->execute([$site+1,'X','parties','SAME','{"is_supplier":0}']);$insert->execute([$site,'Y','parties','SAME','{"is_cust":1}']);
    $pdo->beginTransaction();
    for($i=1;$i<=1011;$i++)$insert->execute([$site,'X','products','P'.$i,'{"name":"型号A"}']);
    $pdo->commit();$before=(int)$pdo->query("SELECT COUNT(*) FROM $old")->fetchColumn();
    // Interrupted upgrade: an incomplete staging table cannot be published when even one field differs.
    $stage=$prefix.CatalogStore::table('parties').'_next';$pdo->exec(CatalogStore::schema('parties',$stage));
    $cols='id,site_id,connection_key,external_id,code,name,enabled,phone_hash,phone_masked,extra_json,seen_run,update_at';
    $pdo->exec("INSERT INTO $stage ($cols) SELECT $cols FROM $old WHERE id=$id");
    $pdo->exec("UPDATE $stage SET name='错误暂存值' WHERE id=$id");$pdo->exec('RENAME TABLE '.$old.' TO '.$old.'_v123');
    try{CatalogUpgrade::run();throw new RuntimeException('inconsistent stage published');}catch(Problem $e){expectMd($e->reason==='CATALOG_MISMATCH','partial/corrupt stage fails closed');}
    expectMd((int)$pdo->query('SELECT COUNT(*) FROM '.$old.'_v123')->fetchColumn()===$before,'archive retained after interruption');
    $pdo->exec("UPDATE $stage SET name='测试档案' WHERE id=$id");
    CatalogUpgrade::run();
    expectMd((int)$pdo->query('SELECT COUNT(*) FROM '.$old.'_v123')->fetchColumn()===$before,'all originals retained');
    expectMd(array_sum(array_map(fn($k)=>CatalogStore::query($k)->count(),CatalogStore::kinds()))===$before,'all rows copied exactly');
    foreach(CatalogStore::kinds() as $k)expectMd(CatalogStore::query($k)->where(['site_id'=>$site,'connection_key'=>'X','external_id'=>'SAME'])->count()===1,'scoped identity '.$k);
    $r=CatalogStore::query('parties')->where('id',$id)->find();expectMd((int)$r['id']===$id && $r['city_name']==='示范市' && (int)$r['is_supplier']===1 && (int)$r['is_cust']===1,'IDs and first-class party columns');
    expectMd(CatalogStore::query('products')->where('model_name','型号A')->count()===1012,'multi-page and indexed SKU name');
    $tables=CatalogStore::kinds();expectMd(count(array_unique(array_map([CatalogStore::class,'table'],$tables)))===10,'ten physical tables');
    try{CatalogStore::table('erp_sale_order');throw new RuntimeException('unsafe target');}catch(Problem $e){expectMd($e->reason==='RESOURCE_INVALID','allowlisted table names');}
    CatalogStore::query('parties')->where('id',$id)->update(['name'=>'独立写入']);
    CatalogUpgrade::run();expectMd(CatalogStore::query('parties')->where('id',$id)->value('name')==='独立写入','repeat does not restore old rows');
    expectMd($pdo->query('SELECT name FROM '.$old.'_v123 WHERE id='.$id)->fetchColumn()==='测试档案','frozen copy unchanged');
    echo json_encode(['passed'=>true,'checks'=>$checks,'migrated'=>$before,'network'=>0]).PHP_EOL;
} catch(Throwable $e){$failed=true;fwrite(STDERR,'FAIL '.$e->getMessage().' '.basename($e->getFile()).':'.$e->getLine().PHP_EOL);}
finally{if($pdo && preg_match('/^hsx_boyuan_test_[a-f0-9]{16}$/D',$schema))$pdo->exec('DROP DATABASE '.$schema);}
exit($failed?1:0);
