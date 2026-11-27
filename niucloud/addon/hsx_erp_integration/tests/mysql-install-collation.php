<?php
declare(strict_types=1);

// Usage: php tests/mysql-install-collation.php --isolated-mysqld /absolute/path/to/mysqld
// Starts its own disposable server. Accepts no existing server address, database, login or socket.
use addon\hsx_erp_integration\Addon;
use app\service\core\addon\CoreAddonInstallService as Installer;
use think\facade\Db;

if (PHP_SAPI !== 'cli' || $argc !== 3 || $argv[1] !== '--isolated-mysqld' || !extension_loaded('pdo_mysql')
    || $argv[2][0] !== '/' || !is_executable($argv[2]) || basename($argv[2]) !== 'mysqld') {
    fwrite(STDERR, "Requires PHP CLI/pdo_mysql and --isolated-mysqld /absolute/path/to/mysqld\n"); exit(2);
}
$bin = realpath($argv[2]);
$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';
require_once $root . '/vendor/topthink/framework/src/helper.php';
require_once $root . '/app/common.php';
$base = '/tmp/bridge-ddl-' . bin2hex(random_bytes(8));
if (!mkdir($base, 0700) || !mkdir($base . '/data', 0700)) throw new RuntimeException('Cannot create isolated datadir');
$socket = "$base/db.sock";
$server = null;
$stdio = [['file','/dev/null','r'], ['file',"$base/process.log",'a'], ['file',"$base/process.log",'a']];
$common = [$bin,'--no-defaults',"--datadir=$base/data","--tmpdir=$base", "--log-error=$base/server.log",
    '--innodb-buffer-pool-size=32M','--skip-log-bin','--performance-schema=OFF'];
$count = 0;
function verify(bool $condition, string $label): void {
    global $count;
    if (!$condition) throw new RuntimeException($label);
    ++$count;
}
try {
    $setup = proc_open([...$common,'--initialize-insecure'], $stdio, $p);
    if (!is_resource($setup) || proc_close($setup) !== 0) throw new RuntimeException('mysqld initialization failed');
    $server = proc_open([...$common, '--skip-networking','--mysqlx=0',"--socket=$socket","--pid-file=$base/server.pid"],$stdio,$p);
    if (!is_resource($server)) throw new RuntimeException('mysqld startup failed');
    $conn = null; $start = microtime(true);
    while (microtime(true)-$start < 20 && proc_get_status($server)['running']) {
        try { $conn = new PDO("mysql:unix_socket=$socket;charset=utf8mb4", 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); break; }
        catch (PDOException $ignored) { usleep(100000); }
    }
    if (!$conn) throw new RuntimeException('Private socket not ready; no fallback');
    verify(realpath((string)$conn->query('SELECT @@datadir')->fetchColumn()) === realpath("$base/data"),'Not our private datadir');
    verify((int)$conn->query('SELECT @@skip_networking')->fetchColumn() === 1, 'TCP not disabled');
    $conn->exec('CREATE DATABASE bridge_fixture CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    $app = new think\App($root); // No application initialization, .env or site-specific credentials.
    $app->config->set(['default'=>'mysql','connections'=>['mysql'=>[
        'type'=>'mysql','socket'=>$socket,'database'=>'bridge_fixture','username'=>'root','password'=>'',
        'charset'=>'utf8mb4','prefix'=>'probe_','fields_cache'=>false]]],'database');
    $app->config->set(['default'=>'file','channels'=>['file'=>['type'=>'File','close'=>true,'path'=>"$base/log/"]]],'log');
    $app->config->set(['default'=>'file','stores'=>['file'=>['type'=>'File','path'=>"$base/cache/"]]],'cache');
    verify(Db::query('SELECT @@collation_database AS c')[0]['c'] === 'utf8mb4_general_ci','Wrong test database collation');
    $broken = "$base/prior.sql";
    file_put_contents($broken, "CREATE TABLE `{{prefix}}prior` (`fact_key` varchar(160) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL) ENGINE=InnoDB;\n");
    try { Installer::executeSql($broken); throw new RuntimeException('Failed to reproduce original error'); }
    catch (Throwable $error) { verify(strpos($error->getMessage(),'1253') !== false && strpos($error->getMessage(),'utf8mb4_general_ci') !== false,'Unexpected prior error: '.$error->getMessage()); }
    echo "Reproduced 1253 through NiuCloud installer at utf8mb4_general_ci.\n";
    $sql = dirname(__DIR__) . '/sql/install.sql';
    $install = static fn() => Installer::executeSql($sql) && (new Addon())->install();
    verify($install(), 'Fresh install failed');
    $table = 'probe_erp_integration_sync_task';
    $cols = array_column(Db::query("SHOW FULL COLUMNS FROM `$table`"),null,'Field');
    foreach (['origin_event_id','fact_key','target_key'] as $key) verify($cols[$key]['Collation'] === null && str_starts_with(strtolower($cols[$key]['Type']),'varbinary('),'Not binary: '.$key);
    $insert = static function (?string $event, ?string $fact, ?string $target) use ($table): void {
        Db::execute("INSERT INTO `$table` (site_id,connection_id,source_id,source_type,business_type,origin_event_id,fact_key,target_key) VALUES (5,1,9,'erp_purchase','purchase',?,?,?)",[$event,$fact,$target]);
    };
    $insert('EventA','FactA','target'); $insert('eventa','facta','target'); $insert(null,null,null); $insert(null,null,null);
    $insert('EventA','FactA','TARGET');
    foreach ([['new-event','FactA'],['EventA','new-fact']] as [$ev,$fact]) {
        try { $insert($ev,$fact,'target'); throw new RuntimeException('Expected duplicate'); }
        catch (Throwable $error) { verify(strpos($error->getMessage(),'1062') !== false,'Expected unique index, got: '.$error->getMessage()); }
    }
    for ($i=0; $i<2; ++$i) {
        verify((new Addon())->uninstall() && $install(), 'Reinstall failed');
        verify((int)Db::query("SELECT COUNT(*) n FROM `$table`")[0]['n']===5,'Existing rows changed');
    }
    verify(count(Db::query('SHOW TABLES'))===5,'Unexpected table count / wrong prefix');
    echo "PASS $count checks: ordinary installer + plugin, fresh install and 2 reinstalls, custom prefix, exact-case identities, NULLs, deduplication and preserved rows.\n";
} catch (Throwable $e) {
    foreach (['server.log','process.log'] as $f) if (is_file("$base/$f")) fwrite(STDERR,substr((string)file_get_contents("$base/$f"),-800)."\n");
    throw $e;
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { if ($f->isLink() || !$f->isDir()) unlink($f->getPathname()); else rmdir($f->getPathname()); }
    rmdir($base);
}
