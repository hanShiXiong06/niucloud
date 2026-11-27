<?php
declare(strict_types=1);

// 默认仅验证数据转换；可选本地数据库事务回归。两种模式都不创建打印任务、不连接打印机。
require dirname(__DIR__) . '/niucloud/vendor/autoload.php';

use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\service\admin\printer\RecyclePrintSceneService;
use addon\hsx_recycle\app\service\admin\printer\RecyclePrinterTemplateService;
use addon\hsx_recycle\app\service\admin\printer\template\VariableReplaceService;
use addon\hsx_recycle\app\service\core\recycle_order\DeviceSummaryHelper;
use think\facade\Db;

$count = 0;
$assert = static function (bool $ok, string $message) use (&$count): void {
    $count++;
    if (!$ok) throw new RuntimeException($message);
};
$service = (new ReflectionClass(RecyclePrinterTemplateService::class))->newInstanceWithoutConstructor();
$invoke = static function (string $name, ...$args) use ($service) {
    $method = new ReflectionMethod($service, $name);
    $method->setAccessible(true);
    return $method->invoke($service, ...$args);
};

try {
    // 签收/代下单写入的真实摘要契约：此时没有完整质检文字可供正则兜底。
    $savedInfo = DeviceSummaryHelper::buildInfo([], ['1'], ['battery' => 96, 'battery_num' => 0]);
    $before = json_encode($savedInfo);
    foreach (['array' => $savedInfo, 'json' => $before, 'object' => json_decode($before)] as $format => $info) {
        $normalized = $invoke('normalizeDeviceInfo', $info);
        $meta = $invoke('getDeviceCheckMeta', [], $normalized);
        $assert(($meta['battery'] ?? null) === 96, "{$format}：签收电池健康度不能丢失");
        $assert(($meta['battery_num'] ?? null) === 0, "{$format}：0 次循环是有效数据");
        $assert(json_encode($info) === json_encode($format === 'json' ? $before : $savedInfo), "{$format}：取数不得修改原数据");
    }

    foreach ([(object)['battery' => 88], '{"battery":88}', ['battery' => 88]] as $meta) {
        $assert($invoke('getDeviceCheckMeta', [], ['check_meta' => $meta])['battery'] === 88, '独立 check_meta 支持对象、JSON、数组');
        $assert($invoke('getDeviceCheckMeta', ['check_meta' => $meta], [])['battery'] === 88, '顶层 check_meta 同口径读取');
    }
    foreach ([null, '', 'not-json', '123', false] as $invalid) {
        $assert($invoke('normalizeDeviceInfo', $invalid) === [], '无效 info 安全返回空数组');
        $assert($invoke('getDeviceCheckMeta', [], ['check_meta' => $invalid]) === [], '无效 meta 安全返回空数组');
    }
    $assert($invoke('isBlankPrintValue', 0) === false, '0 不能显示成横线');
    $assert($invoke('firstNotBlank', null, '', 0, 100) === 0, '取值优先级保留零值');
    $assert($invoke('extractValueFromCheckText', '电池健康度87%', '/电池健康度\s*(\d{1,3})\s*%/u') === '87', '完整质检文字兜底保持有效');

    echo "PASS printer battery conversion: {$count} assertions; no printer calls.\n";
    if (getenv('HSX_RECYCLE_PRINT_ROLLBACK_TEST') !== '1') exit(0);

    // 只允许本机数据库，隔离站点全部回滚。不调用任何 auto/manual 打印执行方法。
    (new think\App(dirname(__DIR__) . '/niucloud/'))->initialize();
    if (!in_array(config('database.connections.mysql.hostname'), ['localhost', '127.0.0.1'], true)) {
        throw new RuntimeException('数据库回归只允许本机测试库');
    }
    $site = 900009180;
    foreach (['recycle_device', 'recycle_order'] as $table) {
        if (Db::name($table)->where('site_id', $site)->count() !== 0) throw new RuntimeException('测试站点已被占用');
        $meta = Db::query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [config('database.connections.mysql.prefix') . $table]);
        if (strtoupper((string)($meta[0]['ENGINE'] ?? '')) !== 'INNODB') throw new RuntimeException('测试表必须支持事务回滚');
    }
    request()->siteId($site);
    request()->uid(0);
    request()->appType('adminapi');
    $template = new RecyclePrinterTemplateService();
    $scene = new RecyclePrintSceneService();
    $resolve = new ReflectionMethod($scene, 'resolveBizPrintData');
    $resolve->setAccessible(true);
    $replace = new VariableReplaceService();
    $checksBefore = $count;
    Db::startTrans();
    try {
        $orderId = Db::name('recycle_order')->insertGetId(['site_id' => $site, 'order_no' => 'TEST-PRINT-' . bin2hex(random_bytes(4)), 'status' => 1, 'device_count' => 4]);
        foreach (['sign-summary' => [96, 0], 'local-reading' => [100, 212], 'zero-health' => [0, 0], 'missing' => [null, null]] as $name => [$health, $cycles]) {
            $summary = $health === null || $name === 'local-reading' ? [] : ['battery' => $health, 'battery_num' => $cycles];
            $device = $name === 'local-reading' ? ['battery_health' => $health, 'battery_cycle' => $cycles] : [];
            $info = DeviceSummaryHelper::buildInfo([], [], $summary, $device);
            $id = (int)Db::name('recycle_device')->insertGetId([
                'site_id' => $site, 'order_id' => $orderId, 'model' => '电池打印隔离测试', 'imei' => 'TEST-' . $name,
                'status' => 1, 'info' => json_encode($info), 'check_template_id' => 0,
                'check_result' => '', 'check_result_seller' => '', 'check_result_buyer' => '',
                'initial_price' => 4500, 'final_price' => 0, 'sell_price' => 0, 'check_at' => 0, 'price_at' => 0,
                'create_at' => time(), 'update_at' => time(),
            ]);
            $modelInfo = (new RecycleDevice())->where('id', $id)->find()->toArray()['info'];
            $assert(is_object($modelInfo), '真实 ORM 将 info 返回为对象，必须覆盖此故障路径');
            $autoData = $resolve->invoke($scene, 'device', ['device_id' => $id, 'order_id' => $orderId, 'biz_id' => $id])['print_data'];
            $manualData = $resolve->invoke($scene, 'device', ['device_id' => $id])['print_data'];
            $expectedHealth = $health === null ? '-' : (string)$health;
            $expectedCycles = $cycles === null ? '-' : (string)$cycles;
            foreach ([$autoData, $manualData] as $print) {
                $assert($print['battery'] === $expectedHealth, "{$name}：没有质检文字时打印健康度正确");
                $assert($print['battery_num'] === $expectedCycles && $print['battery_cycle'] === $expectedCycles, "{$name}：电池循环及别名一致");
                $assert($replace->replaceVariables('电池：{{battery}}% / 循环：{{battery_num}}', $print) === "电池：{$expectedHealth}% / 循环：{$expectedCycles}", "{$name}：模板最终文字正确");
            }
            $assert($autoData['battery'] === $manualData['battery'], "{$name}：自动与手动取值一致");

            // 原来只有生成了完整质检文字之后才可从文本兜底；现在应优先采用已保存的实际值。
            if ($health !== null) {
                Db::name('recycle_device')->where('id', $id)->update(['check_result_seller' => '电池健康度87%']);
                $assert($template->getDevicePrintData($id)['battery'] === $expectedHealth, "{$name}：不能让文字覆盖已保存的结构化电池值");
            } else {
                Db::name('recycle_device')->where('id', $id)->update(['check_result_seller' => '电池健康度87%']);
                $assert($template->getDevicePrintData($id)['battery'] === '87', '仅有质检文字时仍保留兜底');
            }
        }
    } finally {
        Db::rollback();
    }
    foreach (['recycle_device', 'recycle_order'] as $table) {
        $assert(Db::name($table)->where('site_id', $site)->count() === 0, "$table 测试记录已回滚");
    }
    echo 'PASS local database rendering: ' . ($count - $checksBefore) . " assertions; rolled back; no print tasks or physical printing.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}
