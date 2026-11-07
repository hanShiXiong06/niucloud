<?php
declare(strict_types=1);

// PHP 8.0+；执行真实纠错服务，持久化与外部服务替换为隔离内存。
// 不加载环境配置，不连接数据库，不打印、不调用 ERP/财务/外部接口。
namespace { if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } }
namespace CorrectionFixture {
    final class Store {
        public static array $devices = [], $orders = [], $logs = [], $schemas = [], $bindings = [], $nodes = [];
        public static int $saves = 0, $failLogAt = 0;
        public static string $erp = '';
        public static bool $authenticatedMember = false;
        public static function snapshot(): array { return [self::$devices, self::$orders, self::$logs, self::$saves]; }
        public static function restore(array $value): void { [self::$devices, self::$orders, self::$logs, self::$saves] = $value; }
    }
}
namespace core\base { class BaseAdminService { protected $site_id = 100005, $uid = 99, $username = '资料修正员'; } }
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace think\facade {
    final class Db {
        public static function transaction(callable $callback) {
            $snapshot = \CorrectionFixture\Store::snapshot();
            try { return $callback(); } catch (\Throwable $e) { \CorrectionFixture\Store::restore($snapshot); throw $e; }
        }
    }
}
namespace {
    use CorrectionFixture\Store;
    use addon\hsx_recycle\app\service\admin\order\RecycleDeviceInfoCorrectionService;
    $root = dirname(__DIR__);
    require $root . '/app/service/core/recycle_order/DeviceEntryImei.php';
    require $root . '/app/service/core/recycle_order/DeviceReadingArchive.php';
    require $root . '/app/service/admin/order/RecycleDeviceInfoCorrectionService.php';

    final class Service extends RecycleDeviceInfoCorrectionService {
        public function context(int $site = 100005, int $uid = 99): self { $this->site_id = $site; $this->uid = $uid; return $this; }
        protected function loadDevice(int $id, bool $lock): array { $row = Store::$devices[$id] ?? []; return (int)($row['site_id'] ?? 0) === (int)$this->site_id ? $row : []; }
        protected function loadOrder(int $id, bool $lock): array { return Store::$orders[$id] ?? []; }
        protected function loadLogs(int $id): array { return array_values(array_filter(Store::$logs, static fn($row) => (int)$row['device_id'] === $id)); }
        protected function saveDevice(int $id, array $values): bool { Store::$devices[$id] = array_replace(Store::$devices[$id], $values); Store::$saves++; return true; }
        protected function writeLog(array $row): void { if (Store::$failLogAt > 0 && count(Store::$logs) + 1 >= Store::$failLogAt) throw new \RuntimeException('模拟日志故障'); $row['id'] = count(Store::$logs) + 1; Store::$logs[] = $row; }
        protected function selectableLeaf(int $id): array { if (!isset(Store::$nodes[$id])) throw new \RuntimeException('不是有效叶子型号'); return Store::$nodes[$id]; }
        protected function resolveTemplate(int $id): array { return Store::$bindings[$id] ?? []; }
        protected function schema(int $id): array { if (!isset(Store::$schemas[$id])) throw new \RuntimeException('模板不存在'); return Store::$schemas[$id]; }
        protected function erpReason(array $device): string { return Store::$erp; }
        protected function staffActor(int $uid): array { return ['type' => 'staff', 'uid' => $uid, 'member_id' => 0, 'name' => '员工' . $uid, 'known' => true, 'source' => 'staff', 'at' => 123]; }
        protected function isAuthenticatedMemberEntry(array $device): bool { return Store::$authenticatedMember; }
        protected function loadCorrectionBatch(int $id, string $batch): array { return array_values(array_filter(Store::$logs, static fn($row) => (int)$row['device_id'] === $id && strpos((string)($row['remark'] ?? ''), '[' . $batch . ']') === 0)); }
    }
    $count = 0;
    function check(bool $condition, string $message): void { global $count; if (!$condition) throw new \RuntimeException('断言失败：' . $message); $count++; }
    function equal($actual, $expected, string $message): void { check($actual === $expected, $message . '；实际=' . json_encode($actual, JSON_UNESCAPED_UNICODE)); }
    function rejects(callable $callback, string $message, string $fragment = ''): void {
        try { $callback(); } catch (\Throwable $e) { check($fragment === '' || strpos($e->getMessage(), $fragment) !== false, $message . '异常内容：' . $e->getMessage()); return; }
        throw new \RuntimeException('应拒绝但通过：' . $message);
    }
    function field(string $key, string $component = 'input', bool $required = false, array $options = []): array {
        return ['field_key' => $key, 'field_name' => ['battery' => '电池健康度', 'color' => '颜色', 'capacity' => '容量'][$key] ?? $key,
            'component' => $component, 'is_required' => $required ? 1 : 0, 'options' => $options, 'extra_config' => ['summary_visible' => 1]];
    }
    function resetFixture(): Service {
        Store::$devices = [1 => ['id' => 1, 'site_id' => 100005, 'order_id' => 11, 'member_id' => 75, 'status' => 7, 'confirm_status' => 0,
            'pay_status' => 0, 'pay_amount' => '0.00', 'pay_time' => 0, 'category_id' => 8, 'model' => '苹果 iPhone Air', 'check_template_id' => 501,
            'imei' => '353938205192374', 'imei2' => '', 'sn' => 'ABC001', 'user_sn' => '', 'capacity' => '1', 'color' => '2', 'battery' => '88',
            'system_version' => '', 'warranty_info' => '', 'package_type' => '', 'condition_grade' => '',
            'check_uid' => 15, 'check_at' => 999, 'price_uid' => 18, 'price_at' => 1000, 'initial_price' => '4500.00', 'final_price' => '4600.00',
            'before_price' => '原定价历史', 'check_images' => '原始照片', 'check_result' => '原始质检原文',
            'info' => ['operator_note' => '人工备注不动', 'sign_summary' => ['battery' => '80', 'color' => '1', 'old_only' => '旧模板独有'],
                'local' => ['raw' => ['identity' => ['imei' => '353938205192374']]], 'battery' => '85',
                'check_meta' => ['template_id' => 501, 'custom_fields' => ['battery' => '89', 'color' => '2'],
                    'result_items' => [['field_key' => 'battery', 'component' => 'input', 'value' => '90'], ['field_key' => 'color', 'component' => 'radio', 'value' => '2']]]]]];
        Store::$orders = [11 => ['id' => 11, 'site_id' => 100005]];
        Store::$logs = [
            ['id' => 1, 'device_id' => 1, 'operator_id' => 7, 'operator_name' => '原录入员', 'operation_type' => 'add', 'remark' => '设备添加 | 型号: 苹果 iPhone Air', 'create_at' => 100],
            ['id' => 2, 'device_id' => 1, 'operator_id' => 10, 'operator_name' => '第一次质检员', 'operation_type' => 'check_complete', 'create_at' => 500],
            ['id' => 3, 'device_id' => 1, 'operator_id' => 15, 'operator_name' => '当前质检员快照', 'operation_type' => 'check_complete', 'create_at' => 999],
        ];
        Store::$nodes = [8 => ['node_name' => '苹果 iPhone Air', 'category_path' => [1, 2, 8]], 9 => ['node_name' => '小米 17 Pro', 'category_path' => [1, 3, 9]], 10 => ['node_name' => '同模板型号', 'category_path' => [1, 2, 10]]];
        Store::$bindings = [9 => ['check_template_id' => 502], 10 => ['check_template_id' => 501]];
        Store::$schemas = [501 => ['groups' => [['fields' => [field('battery'), field('color', 'radio', false, [['value' => '1', 'label' => '黑色'], ['value' => '2', 'label' => '银色'], ['value' => '3', 'label' => '蓝色']]), field('capacity', 'select', false, [['value' => '1', 'label' => '256GB'], ['value' => '2', 'label' => '512GB']]), field('tags', 'checkbox', false, [['value' => 'a', 'label' => '原装'], ['value' => 'b', 'label' => '全套']]), field('switch', 'switch'), field('quantity', 'number'), field('note')]]]],
            502 => ['groups' => [['fields' => [field('battery'), field('new_required', 'input', true), field('color', 'radio', false, [['value' => '9', 'label' => '釉白']])]]]]];
        Store::$saves = 0; Store::$failLogAt = 0; Store::$erp = ''; Store::$authenticatedMember = false;
        return new Service();
    }

    foreach ([3, 4, 7, 8] as $status) { $service = resetFixture(); Store::$devices[1]['status'] = $status; check($service->describe(Store::$devices[1])['allowed'], '允许确认前状态' . $status); }
    foreach ([1, 2, 5, 6, 9] as $status) { $service = resetFixture(); Store::$devices[1]['status'] = $status; rejects(static fn() => $service->correct(1, ['sn' => 'NEW001']), '拒绝其他状态' . $status); }
    foreach ([['confirm_status', 1], ['confirm_status', 2], ['pay_status', 1], ['pay_amount', '1.00'], ['pay_time', 123], ['downstream_erp_asset_id', 66], ['downstream_stage', 10]] as [$key, $value]) {
        $service = resetFixture(); Store::$devices[1][$key] = $value; $snapshot = Store::snapshot(); rejects(static fn() => $service->correct(1, ['sn' => 'NEW001']), '拒绝' . $key); equal(Store::snapshot(), $snapshot, '门禁失败不写数据');
    }
    $service = resetFixture(); Store::$erp = '设备已存在 ERP 采购或资产关联'; rejects(static fn() => $service->correct(1, ['sn' => 'NEW001']), 'ERP采购即使未成资产也拒绝');
    $service = resetFixture(); Store::$erp = 'ERP核对失败'; rejects(static fn() => $service->correct(1, ['sn' => 'NEW001']), 'ERP故障关闭入口');
    $service = resetFixture(); Store::$devices[1]['site_id'] = 100006; rejects(static fn() => $service->correct(1, ['sn' => 'NEW001']), '隔离站点');
    foreach (['final_price', 'initial_price', 'before_price', 'price_uid', 'price_at', 'check_uid', 'check_at', 'pay_status', 'status', 'check_template_id', 'info'] as $key) {
        $service = resetFixture(); rejects(static fn() => $service->correct(1, [$key => 1]), '只读业务字段' . $key, '不能提交');
    }
    $service = resetFixture(); rejects(static fn() => $service->correct(1, ['correction_reason' => ['不能数组']]), '说明不收数组');
    rejects(static fn() => $service->correct(1, ['correction_reason' => str_repeat('长', 2001)]), '说明限制长度');
    $snapshot = Store::snapshot(); $result = $service->correct(1, ['sn' => 'ABC001', 'summary' => ['battery' => '90']]); equal($result['changed'], false, '相同值不产生假变更'); equal(Store::snapshot(), $snapshot, '无改动不写日志/设备');
    equal($service->currentSummary(Store::$devices[1])['battery'], '90', 'result_items当前值优先，不回退签收80');
    $provenance = $service->describe(Store::$devices[1])['provenance']; equal($provenance['initial_entry']['uid'], 7, '最早资料操作而非当前修改员'); equal($provenance['checker']['uid'], 15, '修改前当前质检UID而非首个质检员'); equal($provenance['checker']['name'], '当前质检员快照', '保留对应员工历史快照'); equal($provenance['checker']['at'], 999, '保存当前质检时间');

    $service = resetFixture(); $before = Store::$devices[1]; $result = $service->correct(1, ['imei' => 'ABC12345', 'summary' => ['battery' => '95', 'color' => '3', 'quantity' => 0, 'switch' => false, 'tags' => ['a', 'b']]]);
    check($result['changed'], '资料真正保存'); $saved = Store::$devices[1];
    foreach (['status', 'confirm_status', 'pay_status', 'pay_amount', 'pay_time', 'initial_price', 'final_price', 'before_price', 'check_uid', 'check_at', 'price_uid', 'price_at', 'check_images', 'check_result'] as $key) equal($saved[$key], $before[$key], '业务字段不动:' . $key);
    equal($saved['info']['local'], $before['info']['local'], '读取原文保留'); equal($saved['info']['sign_summary'], $before['info']['sign_summary'], '同模板不覆盖签收原快照');
    equal($saved['battery'], '95', '电池列也与当前摘要同步'); equal($saved['color'], '3', '颜色列同步原选项key'); equal($service->currentSummary($saved)['quantity'], 0, '数字零不能丢'); equal($service->currentSummary($saved)['switch'], false, 'false不能丢');
    $grouped = $service->groupTimelineLogs([end(Store::$logs)], 1); equal(count($grouped), 1, '只取末片也补查整批日志'); equal($grouped[0]['integrity'], 'complete', '分片完整重组'); check(strpos($grouped[0]['remark'], '银色 → 蓝色') !== false, '摘要日志显示选项文字而非2到3');
    $changeMap = array_column($grouped[0]['changes'], null, 'field'); equal($changeMap['summary.color']['before'], '2', '日志同时保留原始key'); equal($changeMap['summary.color']['after_display'], '蓝色', '日志保留人类可读新值');

    $service = resetFixture(); $service->correct(1, ['capacity' => '2']); equal(Store::$devices[1]['info']['capacity'], '2', '仅top-level容量也同步info'); equal($service->currentSummary(Store::$devices[1])['capacity'], '2', 'top-level不会被旧info覆盖');
    $service = resetFixture(); Store::$devices[1]['info']['check_meta']['custom_fields']['tags'] = ['a']; $service->correct(1, ['summary' => ['battery' => '', 'tags' => []]]); equal($service->currentSummary(Store::$devices[1])['battery'], '', '显式清空输入'); equal($service->currentSummary(Store::$devices[1])['tags'], [], '显式清空多选'); equal($service->currentSummary(Store::$devices[1])['color'], '2', '未提交字段保持当前值');
    $service = resetFixture(); Store::$schemas[501]['groups'][0]['fields'][] = field('later_required', 'input', true); $service->correct(1, ['imei' => 'NEW12345']); check(true, '后加必填字段不阻止单独改串号');
    rejects(static fn() => $service->correct(1, ['summary' => ['later_required' => '']]), '本次提交的必填不得清空', '请填写');
    rejects(static fn() => $service->correct(1, ['summary' => ['color' => '999']]), '未知选项拒绝'); rejects(static fn() => $service->correct(1, ['summary' => ['not_visible' => 'x']]), '未启用摘要字段拒绝');

    $service = resetFixture(); rejects(static fn() => $service->correct(1, ['category_id' => 9, 'summary' => ['battery' => '95']]), '换模板校验全部必填');
    $before = Store::$devices[1]; $service->correct(1, ['category_id' => 9, 'model' => '伪造型号', 'summary' => ['new_required' => '核对后填写', 'color' => '9']]); $saved = Store::$devices[1];
    equal($saved['model'], '小米 17 Pro', '型号名称来自可信末级节点'); equal($saved['check_template_id'], 502, '新型号明确绑定新模板'); equal($saved['check_uid'], 15, '换型号不重跑质检');
    equal($saved['info']['info_correction_audit']['original_templates']['501']['sign_summary'], $before['info']['sign_summary'], '签收原文只归档，不丢证据');
    check(!isset($saved['info']['sign_summary']), '旧签收答案从当前视图隔离'); check(!isset($service->currentSummary($saved)['old_only']), '新模板不混旧签收独有字段'); equal($service->currentSummary($saved)['battery'], '', '新模板未知电池不套旧值/默认');
    $archive = $saved['info']['info_correction_audit']['original_templates']['501']; $service->correct(1, ['summary' => ['battery' => '96']]); equal(Store::$devices[1]['info']['info_correction_audit']['original_templates']['501'], $archive, '多次纠错不覆盖第一份原质检证据');
    $service = resetFixture(); $service->correct(1, ['category_id' => 10]); equal(Store::$devices[1]['info']['sign_summary']['battery'], '80', '同模板切分类保留签收证据'); equal($service->currentSummary(Store::$devices[1])['battery'], '90', '同模板切分类保留当前质检答案');
    $service = resetFixture(); Store::$bindings[9] = []; rejects(static fn() => $service->correct(1, ['category_id' => 9]), '没有明确绑定不能套全局默认');

    $service = resetFixture(); Store::$failLogAt = count(Store::$logs) + 2; $snapshot = Store::snapshot(); rejects(static fn() => $service->correct(1, ['sn' => 'ROLLBACK']), '日志途中失败'); equal(Store::snapshot(), $snapshot, '日志失败事务回滚设备和已写日志');
    $service = resetFixture(); $long = str_repeat('这是完整原始长内容😀', 400); Store::$devices[1]['info']['check_meta']['custom_fields']['note'] = $long;
    $service->correct(1, ['summary' => ['note' => strrev('abcdef') . $long], 'correction_reason' => str_repeat('说明', 999)]);
    foreach (Store::$logs as $row) if (($row['operation_type'] ?? '') === 'info_correction') check(preg_match_all('/./us', $row['remark'], $unused) <= 255, '每条日志不超过既有255字符');
    $grouped = $service->groupTimelineLogs([end(Store::$logs)], 1); equal($grouped[0]['integrity'], 'complete', '大于50片也完整补查'); equal($grouped[0]['changes'][0]['before'], $long, '长旧值完整未截断'); equal($grouped[0]['changes'][0]['after'], 'fedcba' . $long, '长新值完整未截断');
    array_pop(Store::$logs); $grouped = $service->groupTimelineLogs([end(Store::$logs)], 1); equal($grouped[0]['integrity'], 'incomplete', '缺片明确提醒'); equal($grouped[0]['changes'], [], '缺片不伪装完整前后值');

    $service = resetFixture(); Store::$logs = []; $service->correct(1, ['sn' => 'NEW001']); $provenance = $service->describe(Store::$devices[1])['provenance']; equal($provenance['initial_entry']['known'], false, '历史无首录证据标未知，不猜当前操作人');
    $service = resetFixture(); Store::$logs = []; \think\facade\Db::transaction(static fn() => $service->recordInitialEntry(1, 'staff_entry', 100005)); $snapshot = Store::$devices[1]['info']['entry_audit']['initial_entry']; equal($snapshot['uid'], 99, '新录入捕获真实管理员'); $service->context(100005, 88)->recordInitialEntry(1, 'staff_entry', 100005); equal(Store::$devices[1]['info']['entry_audit']['initial_entry'], $snapshot, '首录快照不可被后续覆盖');
    $service = resetFixture()->context(0, 0); Store::$logs = []; $service->recordInitialEntry(1, 'customer_order', 100005); $actor = Store::$devices[1]['info']['entry_audit']['initial_entry']; equal($actor['known'], false, '无web请求核心建单不伪造客户手录'); equal($actor['member_id'], 0, '设备归属会员不当作真实操作人');
    $service = resetFixture()->context(100005, 0); Store::$authenticatedMember = true; $service->recordInitialEntry(1, 'customer_order', 100005); equal(Store::$devices[1]['info']['entry_audit']['initial_entry']['member_id'], 75, '可信客户认证下记录客户本人');
    $service = resetFixture()->context(100005, 0); Store::$logs = []; Store::$authenticatedMember = true; Store::$devices[1]['model'] = ''; Store::$devices[1]['category_id'] = 1;
    $service->recordInitialEntry(1, 'customer_order', 100005); check(!isset(Store::$devices[1]['info']['entry_audit']['initial_entry']), '客户IMEI空壳不占据型号资料首录人'); equal(Store::$devices[1]['info']['entry_audit']['record_creator']['member_id'], 75, '空壳创建人另行保留');
    $blankBefore = Store::$devices[1]; Store::$devices[1]['model'] = '苹果 iPhone Air'; Store::$devices[1]['category_id'] = 8; $service->context(100005, 42)->recordFirstModelFill(1, $blankBefore, 'sign_first_info', 100005);
    equal(Store::$devices[1]['info']['entry_audit']['initial_entry']['uid'], 42, '员工首次签收补型号算真实首录'); equal(Store::$devices[1]['info']['entry_audit']['record_creator']['member_id'], 75, '实际首录不覆盖客户创建占位责任');
    $filledBefore = Store::$devices[1]; $service->context(100005, 43)->recordFirstModelFill(1, $filledBefore, 'check_first_info', 100005); equal(Store::$devices[1]['info']['entry_audit']['initial_entry']['uid'], 42, '后续质检不能抢走首次型号录入责任');
    $service = resetFixture(); Store::$logs = []; $before = Store::$devices[1]; $service->recordFirstModelFill(1, $before, 'sign_first_info', 100005); check(!isset(Store::$devices[1]['info']['entry_audit']['initial_entry']), '历史已有型号未记录不补造当前员工责任');
    $service = resetFixture(); Store::$logs = []; Store::$failLogAt = 1; $snapshot = Store::snapshot(); rejects(static fn() => \think\facade\Db::transaction(static fn() => $service->recordInitialEntry(1, 'staff_entry', 100005)), '新增首录日志失败'); equal(Store::snapshot(), $snapshot, '首录日志失败一并回滚');

    $deviceSource = file_get_contents($root . '/app/service/admin/order/RecycleDeviceService.php');
    check(strpos($deviceSource, "return Db::transaction(function () use (\$data, \$siteId): int") !== false, '真实单设备add使用事务');
    check(strpos($deviceSource, "if (\$signSave) (new RecycleDeviceInfoCorrectionService())->recordFirstModelFill(\$id, \$beforeBasicInfo, 'sign_first_info', (int)\$this->site_id);") !== false, 'PC待签收设备先保存入口同事务捕获首次实际型号录入');
    $coreSource = file_get_contents($root . '/app/service/core/recycle_order/CoreRecycleOrderService.php');
    check(strpos($coreSource, "'staff_order', (int)\$data['site_id']") !== false, '核心创建传可信业务站点，不依赖web上下文');
    $signSource = file_get_contents($root . '/app/service/core/recycle_order/handler/SignHandler.php');
    check(strpos($signSource, "->signUpdate((int)\$device['id'], \$deviceData, \$siteId)") !== false, '签收传可信流程siteId不依赖web请求');
    check(strpos($signSource, "->add(\$deviceData, \$siteId)") !== false, '新增签收设备传可信流程siteId');

    require dirname($root) . '/hsx_erp/app/support/ErpRecycleDeviceIdentity.php';
    require dirname($root) . '/hsx_erp/app/service/admin/ErpRecycleDeviceIdentityService.php';
    final class ErpLinks extends \addon\hsx_erp\app\service\admin\ErpRecycleDeviceIdentityService {
        public array $items = [];
        public function deviceAssets(int $siteId, array $deviceIds): array { $result = []; foreach ($deviceIds as $id) $result[$id] = ['has_asset' => $id === 4, 'ambiguous' => $id === 5]; return $result; }
        protected function loadRecyclePurchaseItems(int $siteId): array { return $this->items; }
    }
    $erp = new ErpLinks(); $erp->items = [
        ['site_id' => 100005, 'spec_json' => ['source_device_id' => 2, 'source_plugin' => 'hsx_recycle']],
        ['site_id' => 100006, 'spec_json' => ['source_device_id' => 1, 'source_plugin' => 'hsx_recycle']],
        ['site_id' => 100005, 'spec_json' => json_encode(['source_device_id' => '3', 'source_plugin' => 'wrong_plugin'])],
    ];
    $links = $erp->infoCorrectionLinks(100005, [1, 2, 3, 4, 5]);
    equal($links[1], ['has_asset' => false, 'has_purchase' => false, 'ambiguous' => false], '其他站同设备ID不能污染门禁');
    equal($links[2]['has_purchase'], true, '真实ERP解析覆盖purchase-only无资产关联');
    equal($links[3]['ambiguous'], true, '采购快照插件冲突也阻止纠错'); equal($links[4]['has_asset'], true, '资产关联阻止纠错'); equal($links[5]['ambiguous'], true, '多义资产关系阻止纠错');
    echo '[通过] 基础资料纠错隔离回归 ' . $count . " 项；未连接数据库、ERP、支付、打印或外网。\n";
}
