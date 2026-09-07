<?php
declare(strict_types=1);

// 专用空站点、本地 InnoDB、整段事务回滚；不调用真实设备/保修/支付服务。
if (getenv('HSX_DEVICE_READINGS_ROLLBACK_TEST') !== '1') {
    fwrite(STDERR, "Set HSX_DEVICE_READINGS_ROLLBACK_TEST=1 to run.\n"); exit(2);
}
require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\third_party\DeviceQueryResult;
use addon\hsx_recycle\app\service\admin\order\RecycleDeviceService;
use addon\hsx_recycle\app\service\admin\order\RecycleOrderDeviceService;
use addon\hsx_recycle\app\service\admin\order\RecycleDeviceErpSyncService;
use addon\hsx_recycle\app\service\core\recycle_order\DeviceReadingArchive;
use addon\hsx_recycle\app\service\core\recycle_order\DeviceSummaryHelper;
use addon\hsx_recycle\app\service\core\device_query\DeviceQueryResultRecorder;
use addon\hsx_recycle\app\service\core\device_query\CoreDeviceQueryService;
use addon\hsx_erp\app\listener\ErpDeviceInboundRequested;
use addon\hsx_erp\app\service\admin\ErpStockService;
use addon\hsx_erp\app\model\ErpAsset;
use think\facade\Db;

(new think\App())->initialize();
set_exception_handler(static function(Throwable $e): void { fwrite(STDERR, $e->getFile().':'.$e->getLine().' '.$e->getMessage()."\n".$e->getTraceAsString()."\n"); exit(1); });
if (!in_array(config('database.connections.mysql.hostname'), ['localhost', '127.0.0.1'], true)) throw new RuntimeException('仅允许本地数据库');
$site = 900000022;
$tables = ['recycle_order', 'recycle_device', 'recycle_device_log', 'recycle_device_model_dict', 'device_query_result', 'erp_asset'];
foreach ($tables as $table) {
    $engine = Db::query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [config('database.connections.mysql.prefix').$table]);
    if (strtoupper((string)($engine[0]['ENGINE'] ?? '')) !== 'INNODB') throw new RuntimeException('样本表不支持回滚：'.$table);
    if (Db::name($table)->where('site_id', $site)->count() > 0) throw new RuntimeException('测试站点已有数据，禁止继续');
}
if (Db::name('sys_config')->where('site_id', $site)->count() > 0 || Db::name('site')->where('site_id', $site)->count() > 0) throw new RuntimeException('测试站点已有配置，禁止触发其业务');
request()->siteId($site); request()->uid(1); request()->username('设备采集回滚测试'); request()->appType('adminapi');
$count = 0;
$assert = static function(bool $ok, string $message) use (&$count): void { $count++; if (!$ok) throw new RuntimeException($message); };
$reject = static function(callable $fn, string $message) use ($assert): void {
    try { $fn(); } catch (\core\exception\CommonException $e) { $assert(true, $message); return; }
    $assert(false, $message);
};
$invoke = static function(object $object, string $name, array $args) {
    $method = new ReflectionMethod($object, $name); $method->setAccessible(true); return $method->invokeArgs($object, $args);
};
$suffix = date('His').bin2hex(random_bytes(3));
$imei = 'READ-'.$suffix;
$raw = [
    'schema_version'=>'hsx.device.snapshot.v1', 'captured_at'=>'2026-09-07T00:51:38+00:00', 'source'=>'usb', 'platform'=>'ios',
    'identity'=>['imei'=>$imei, 'imei2'=>$imei.'2', 'serial_number'=>'SN-'.$suffix, 'udid'=>'TEST-UDID'],
    'hardware'=>['product_type'=>'iPhone18,4', 'model_number'=>'MG364', 'color_code'=>'1'],
    'display'=>['device_name'=>'iPhone Air', 'capacity'=>'256GB', 'color'=>''],
    'system'=>['version'=>'26.6'],
    'battery'=>['health_percent'=>100, 'calculated_health_percent'=>100.5, 'cycle_count'=>212, 'level_percent'=>98]
];
$deviceId = 0;
Db::startTrans();
try {
    $categoryId = (int)Db::name('recycle_device_model_dict')->insertGetId(['site_id'=>$site, 'pid'=>0, 'level'=>1, 'node_name'=>'采集测试型号-'.$suffix, 'model_full_name'=>'采集测试型号-'.$suffix, 'status'=>1]);
    $orderId = (int)Db::name('recycle_order')->insertGetId(['site_id'=>$site, 'order_no'=>'READ-'.$suffix, 'flow_mode'=>'device', 'member_id'=>0, 'status'=>RecycleOrderDict::ORDER_STATUS_SIGNED, 'device_count'=>0, 'count'=>0, 'create_at'=>time(), 'update_at'=>time()]);
    $queryPayload = [
        'site_id'=>$site, 'query_code'=>$imei, 'query_type'=>'imei', 'service_code'=>'apple_coverage',
        'service'=>['name'=>'采集测试保修', 'query_type'=>'imei'],
        'channel'=>['key'=>'test', 'name'=>'隔离假数据', 'provider'=>'test'],
        'mapping'=>['endpoint_value'=>'/test/coverage'],
        'provider_result'=>['third_code'=>0, 'raw_response'=>['coverage_end'=>'2027-09-01', 'untouched'=>'raw-marker']],
        'normalized_result'=>['coverage'=>['status'=>'In Warranty', 'date'=>'2027-09-01']], 'success'=>true,
    ];
    $recordId = (new DeviceQueryResultRecorder())->record($queryPayload);
    $assert($recordId > 0, '查询台账必须返回记录 ID');
    $archive = ['version'=>1, 'local'=>['raw'=>$raw, 'normalized'=>['battery_health'=>999]],
        'model_match'=>['strategy'=>'exact', 'category_id'=>$categoryId, 'candidates'=>['iPhone18,4']],
        'external_queries'=>[['query_record_id'=>$recordId, 'raw_response'=>['forged'=>true]]]];
    $deviceInput = ['imei'=>$imei, 'imei2'=>$imei.'2', 'serial_number'=>'SN-'.$suffix, 'category_id'=>$categoryId,
        'model'=>'iPhone Air', 'initial_price'=>4500, 'capacity'=>'256GB', 'color'=>'', 'system_version'=>'26.6',
        'warranty_info'=>'保 2027-09-01', 'battery_health'=>'100', 'battery_cycle'=>212, 'device_readings'=>$archive, 'remark'=>'客户自带包装'];
    $deviceId = (new RecycleOrderDeviceService())->addDeviceToOrder($orderId, $deviceInput);
    $saved = RecycleDevice::findOrEmpty($deviceId)->toArray();
    $saved['info'] = DeviceReadingArchive::decode($saved['info']);
    $stored = $saved['info']['device_readings'];
    $assert($saved['imei2'] === $imei.'2' && $saved['sn'] === 'SN-'.$suffix, '真实新增入口保留 IMEI2 和 SN');
    $assert($stored['local']['raw'] == $raw, '真实新增入口完整保留原始 JSON（数据库可调整对象键顺序）');
    $assert($stored['local']['normalized']['battery_health'] === '100', '后端重新校验健康度来源，不信任客户端伪造的标准化值');
    $assert((string)$saved['info']['check_meta']['battery'] === '100' && (int)$saved['info']['check_meta']['battery_num'] === 212, '健康度/循环次数分开落库');
    $assert($stored['external_queries'][0]['raw_response']['raw']['untouched'] === 'raw-marker', '外部原文从查询台账加载而非客户端原文');
    $assert(!isset($stored['external_queries'][0]['raw_response']['forged']), '拒绝客户端伪造外部原文');
    $assert($saved['remark'] === '客户自带包装', '原始数据不拼进设备备注');
    $assert((int)$stored['model_match']['category_id'] === $categoryId && $stored['model_match']['model'] === $saved['model'], '分类记录使用实际保存的标准型号');

    $editedArchive = $archive; $editedArchive['local']['raw']['battery']['health_percent'] = 9;
    (new RecycleDeviceService())->update($deviceId, ['imei'=>$imei, 'imei2'=>$imei.'2', 'serial_number'=>'SN-'.$suffix,
        'summary'=>['battery'=>95, 'battery_num'=>0], 'device_readings'=>$editedArchive, 'info'=>['device_readings'=>['forged'=>true]]]);
    $edited = RecycleDevice::findOrEmpty($deviceId)->toArray();
    $edited['info'] = DeviceReadingArchive::decode($edited['info']);
    $assert((int)$edited['info']['check_meta']['battery'] === 95 && (int)$edited['info']['check_meta']['battery_num'] === 0, '人工确认覆盖业务值，零循环不丢失');
    $assert($edited['info']['device_readings']['local']['raw'] == $raw, '编辑不能覆盖已有采集原文');
    $assert(count($edited['info']['device_readings']['external_queries']) === 1, '重复提交相同查询记录不重复归档');
    $assert($edited['warranty_info'] === '保 2027-09-01', '未修改的保修独立列保留');
    (new RecycleDeviceService())->update($deviceId, ['info'=>['check_result'=>'manual']]);
    $assert(DeviceReadingArchive::decode(RecycleDevice::findOrEmpty($deviceId)->info)['device_readings']['local']['raw'] == $raw, '普通 info 编辑不能删除原文');
    // 测真实质检暂存，隔离对外事件；空站点没有打印和通知配置。
    \think\facade\Event::remove('AfterDeviceCheckComplete');
    (new RecycleDeviceService())->completeCheck($deviceId, ['info'=>['check_meta'=>['template_id'=>0], 'coverage'=>['status'=>'', 'date'=>'']]], '客户自带包装', 'save_draft');
    $checked = RecycleDevice::findOrEmpty($deviceId)->toArray();
    $checkedInfo = DeviceReadingArchive::decode($checked['info']);
    $assert($checkedInfo['device_readings']['local']['raw'] == $raw, '质检暂存不能覆盖原文');
    $assert((int)$checkedInfo['check_meta']['battery'] === 95 && (int)$checkedInfo['check_meta']['battery_num'] === 0, '质检未提供电池时保留已确认值');
    $assert($checked['warranty_info'] === '保 2027-09-01', '质检空保修结果不能把已知保修改成未激活');

    $reject(fn()=>DeviceReadingArchive::merge([], $archive, array_replace($deviceInput, ['imei'=>'wrong', 'imei2'=>'wrong2', 'serial_number'=>'wrong3']), $site), '不同设备不能串档');
    $reject(fn()=>DeviceReadingArchive::merge([], ['external_queries'=>[['query_record_id'=>$recordId]]], $deviceInput, $site+1), '外部记录强制站点隔离');
    $reject(fn()=>DeviceReadingArchive::merge([], ['external_queries'=>[['query_record_id'=>$recordId]]], ['imei'=>'other'], $site), '外部记录强制串号核对');
    $reject(fn()=>DeviceReadingArchive::merge(['external_queries'=>$stored['external_queries']], [], ['imei'=>'other'], $site), '修改设备身份也不能错挂已有外部记录');
    $reject(fn()=>DeviceReadingArchive::merge([], ['external_queries'=>['bad-format']], $deviceInput, $site), '格式错误给出业务提示');
    $tooBig = $archive; $tooBig['local']['raw']['extra'] = str_repeat('x', 131073);
    $reject(fn()=>DeviceReadingArchive::merge([], $tooBig, $deviceInput, $site), '大体积原文明确提示，不静默截断');
    $assert(DeviceReadingArchive::batteryHealth(['battery'=>['level_percent'=>98]])['value'] === null, '电量不能当健康度');
    $assert(DeviceReadingArchive::batteryHealth(['battery'=>['health_percent'=>0]])['value'] === 0, '零健康度保留');
    $assert(DeviceReadingArchive::batteryHealth(['battery'=>['calculated_health_percent'=>100.5]])['value'] === 100, '仅无健康度时回退计算值且不超过 100');
    $assert(DeviceSummaryHelper::normalizeSummary(['battery'=>0, 'device_readings'=>['forged'=>true]]) === ['battery'=>0], '摘要不可注入原始档案');

    // 连续命中缓存仍指向第一次真实查询，不续期、不嵌套多层原文。
    $coreQuery = new CoreDeviceQueryService();
    $cacheArgs = [$site, $imei, ['code'=>'apple_coverage', 'name'=>'采集测试保修', 'cache_ttl'=>3600], []];
    $firstCache = $invoke($coreQuery, 'getCache', $cacheArgs);
    $secondCache = $invoke($coreQuery, 'getCache', $cacheArgs);
    $assert((int)$firstCache['query_record_id'] === $recordId && (int)$secondCache['query_record_id'] === $recordId, '缓存稳定引用外部采集记录');
    $assert((int)$secondCache['queried_at'] === (int)DeviceQueryResult::findOrEmpty($recordId)->create_at, '缓存保留真实查询时间');

    // 验证回收快照 -> ERP 入库映射 -> 库存详情回显。无需真的付款/触发远程同步。
    $edited['info']['check_meta']['battery'] = 100;
    $edited['info']['check_meta']['battery_num'] = 212;
    $snapshot = $invoke(new RecycleDeviceErpSyncService(), 'buildSnapshot', [$edited]);
    $assert((int)$snapshot['battery_health'] === 100 && (int)$snapshot['battery_cycle_count'] === 212, '回收入库事件携带独立健康度/循环次数');
    $item = $invoke(new ErpDeviceInboundRequested(), 'mapDeviceItem', [$snapshot, 0, 0, 4500.0, 'hsx_recycle', '回收']);
    $assert($item['battery'] === 100, 'ERP battery 列使用健康度');
    $assert(date('Y-m-d', $item['warranty']) === '2027-09-01', '明确保修日期映射为 ERP 日期');
    $assert($item['spec_json']['battery_cycle_count'] === 212 && $item['spec_json']['system_version'] === '26.6', 'ERP 扩展规格保留循环次数/系统版本');
    $qc = DeviceReadingArchive::decode($item['qc_report']);
    $assert($qc['device_readings']['local']['raw'] == $raw, 'ERP 质检档案保留原文');
    $assert($item['quality_remark'] === '客户自带包装' && $item['remark'] === '', 'ERP 质检备注仅是人工表达，未混入原文');
    $assetId = (int)Db::name('erp_asset')->insertGetId(['site_id'=>$site, 'asset_no'=>'READ-'.$suffix, 'imei'=>$imei,
        'model'=>$saved['model'], 'status'=>'in_stock', 'battery'=>$item['battery'], 'warranty'=>$item['warranty'],
        'qc_report'=>$item['qc_report'], 'spec_json'=>json_encode($item['spec_json']), 'purchase_cost'=>4500, 'total_cost'=>4500, 'create_at'=>time()]);
    $stock = ErpStockService::forSite($site, 1, '设备采集回滚测试');
    $detail = $stock->info($assetId);
    $assert($detail['device_readings']['local']['raw'] == $raw && (int)$detail['battery'] === 100, '库存详情回显正确业务值和内部档案');
    $public = $invoke($stock, 'marketplaceQcSnapshot', [ErpAsset::findOrEmpty($assetId), '']);
    $assert(!str_contains(json_encode($public), 'TEST-UDID') && !isset($public['device_readings']), '商城发布不能携带内部采集档案');
} finally {
    Db::rollback();
}
foreach ($tables as $table) $assert(Db::name($table)->where('site_id', $site)->count() === 0, '样本已回滚：'.$table);
echo "PASS device readings database integration: {$count} assertions; all fixtures rolled back.\n";
