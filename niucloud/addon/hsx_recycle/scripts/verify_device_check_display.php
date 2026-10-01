<?php
declare(strict_types=1);

// Isolated fixtures only: no .env, business database, orders or external services.
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
}
namespace core\base {
    class BaseAdminService { public int $site_id = 100005; }
    class BaseApiService { public int $site_id = 100005; }
    class BaseModel extends \think\Model {}
}
namespace {
    use think\facade\Db;
    use addon\hsx_recycle\app\service\core\recycle_order\DeviceCheckDisplayService;
    use addon\hsx_recycle\app\service\admin\order\RecycleDeviceService;
    use addon\hsx_recycle\app\service\api\recycle_order\RecycleOrderService;

    Db::setConfig(['default' => 'isolated', 'connections' => ['isolated' => [
        'type' => 'sqlite', 'database' => ':memory:', 'prefix' => 'test_', 'fields_strict' => true,
    ]]]);
    foreach ([
        'recycle_check_template' => 'id INTEGER PRIMARY KEY, site_id INTEGER, schema_json TEXT',
        'recycle_check_field' => 'id INTEGER PRIMARY KEY, site_id INTEGER, template_id INTEGER, field_key TEXT, field_name TEXT, component TEXT, unit TEXT, extra_config TEXT',
        'recycle_check_option' => 'id INTEGER PRIMARY KEY, site_id INTEGER, field_id INTEGER, option_value TEXT, option_label TEXT, extra_config TEXT',
        'recycle_check_dict' => 'id INTEGER PRIMARY KEY, site_id INTEGER, dict_type TEXT, text TEXT, severity TEXT',
    ] as $name => $fields) Db::execute('CREATE TABLE test_' . $name . ' (' . $fields . ')');

    $checks = 0;
    function same($expected, $actual, string $message): void {
        if ($expected !== $actual) throw new \RuntimeException($message . ': ' . json_encode([$expected, $actual], JSON_UNESCAPED_UNICODE));
        $GLOBALS['checks']++;
        echo 'PASS ' . $message . PHP_EOL;
    }
    function summary(array $device): array { return array_column($device['check_summary'], null, 'field_key'); }

    Db::name('recycle_check_template')->insertAll([
        ['id' => 11, 'site_id' => 100005, 'schema_json' => ''],
        ['id' => 12, 'site_id' => 100005, 'schema_json' => json_encode(['groups' => [['fields' => [
            ['field_key' => 'capacity', 'field_name' => '存储容量', 'component' => 'radio', 'options' => [['name' => '128GB'], ['name' => '512GB']]],
            ['field_key' => 'color', 'field_name' => '机身颜色', 'component' => 'radio', 'options' => [['value' => '1', 'label' => '黑色'], ['value' => '2', 'label' => '蓝色']]],
            ['field_key' => 'battery', 'field_name' => '电池健康度', 'component' => 'number', 'unit' => '%', 'extra_config' => ['summary_visible' => 1]],
        ]]]], JSON_UNESCAPED_UNICODE)],
        ['id' => 13, 'site_id' => 100024, 'schema_json' => json_encode(['groups' => [['fields' => [['field_key' => 'color', 'field_name' => '其他站点', 'options' => [['value' => '2', 'label' => '其他站点私有颜色']]]]]]], JSON_UNESCAPED_UNICODE)],
    ]);
    Db::name('recycle_check_field')->insertAll([
        ['id' => 21, 'site_id' => 100005, 'template_id' => 11, 'field_key' => 'capacity', 'field_name' => '存储容量', 'component' => 'radio', 'unit' => '', 'extra_config' => '{}'],
        ['id' => 22, 'site_id' => 100005, 'template_id' => 11, 'field_key' => 'color', 'field_name' => '机身颜色', 'component' => 'radio', 'unit' => '', 'extra_config' => '{}'],
    ]);
    Db::name('recycle_check_option')->insertAll([
        ['id' => 1, 'site_id' => 100005, 'field_id' => 21, 'option_value' => '2', 'option_label' => '256GB', 'extra_config' => '{}'],
        ['id' => 2, 'site_id' => 100005, 'field_id' => 21, 'option_value' => '3', 'option_label' => '1TB', 'extra_config' => '{}'],
        ['id' => 3, 'site_id' => 100005, 'field_id' => 22, 'option_value' => '2', 'option_label' => '银色', 'extra_config' => '{}'],
    ]);
    $service = new DeviceCheckDisplayService();
    $device = ['id' => 1, 'check_template_id' => 11, 'capacity' => '2', 'color' => '2', 'info' => '{}'];
    $result = $service->enrichDevices([$device], 100005)[0];
    same('2', $result['capacity'], '原始设备属性不被文案覆盖');
    same('256GB', summary($result)['capacity']['label'], '正式value优先于其他选项主键');
    same('银色', summary($result)['color']['label'], '同一数字按字段隔离解析');
    same('存储容量', summary($result)['capacity']['field_name'], '返回字段名称');
    same('2', summary($result)['capacity']['value'], '摘要保留原始值');
    same(true, summary($result)['capacity']['resolved'], '提供是否成功解析');

    $compact = ['id' => 2, 'capacity' => '2', 'color' => '-1002002', 'info' => (object)['check_meta' => (object)['template_id' => 12], 'sign_summary' => (object)['battery' => 0]]];
    $resolved = $service->enrichDevices([$compact], 100005)[0];
    same('512GB', summary($resolved)['capacity']['label'], 'JSON模板的序号值');
    same('蓝色', summary($resolved)['color']['label'], 'JSON模板的临时选项ID');
    same('0', summary($resolved)['battery']['label'], '数值零不当成空值或选项ID');
    same('%', summary($resolved)['battery']['unit'], '模板单位');

    $reportDevice = ['check_template_id' => 12, 'info' => ['check_meta' => ['result_items' => [
        ['field_key' => 'capacity', 'values' => ['2']], ['field_key' => 'color', 'values' => ['1', '2']],
    ]]]];
    $report = $service->enrichDevices([$reportDevice], 100005)[0];
    same('存储容量', $report['info']['check_meta']['result_items'][0]['field_name'], '报告key补齐字段名称');
    same(['512GB'], $report['info']['check_meta']['result_items'][0]['labels'], '报告值补齐文案');
    same(['黑色', '蓝色'], $report['info']['check_meta']['result_items'][1]['labels'], '多选值逐项解析');
    same(['2'], summary($report)['capacity']['value'], '仅报告含值也可生成基本信息');

    $reportDevice['info']['check_meta']['result_items'][0]['text'] = '容量检测：通过';
    $reportDevice['info']['check_meta']['result_items'][0]['option_items'] = [['value' => '2', 'label' => '2', 'style' => ['text_color' => '#123456']]];
    $enrichedItem = $service->enrichDevices([$reportDevice], 100005)[0]['info']['check_meta']['result_items'][0];
    same('容量检测：通过', $enrichedItem['text'], '保留已生成的报告文案');
    same('512GB', $enrichedItem['option_items'][0]['label'], '展示选项同样还原文案');
    same(['text_color' => '#123456'], $enrichedItem['option_items'][0]['style'], '保留选项自定义样式');

    $unknown = $service->enrichDevices([['check_template_id' => 13, 'capacity' => '123', 'color' => '2']], 100005)[0];
    same(false, summary($unknown)['color']['resolved'], '不读取其他站点的模板');
    same('2', summary($unknown)['color']['label'], '无法解析时不伪造文案');
    same(false, summary($unknown)['capacity']['resolved'], '未知数字显式标记未解析');
    $batch = $service->enrichDevices([$device, $compact], 100005);
    same('256GB', summary($batch[0])['capacity']['label'], '批量解析不串模板');
    same('512GB', summary($batch[1])['capacity']['label'], '批量解析保持顺序');
    same([], $service->enrichDevices([], 100005), '空列表');

    $admin = (new \ReflectionClass(RecycleDeviceService::class))->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($admin, 'attachCheckSummary');
    $method->setAccessible(true);
    same($result, $method->invoke($admin, $device), '后台复用公共解析');
    $api = (new \ReflectionClass(RecycleOrderService::class))->newInstanceWithoutConstructor();
    same($result, $api->applyInspectionSeverity([$device])[0], '用户设备接口复用相同解析');
    same(['512GB'], $api->applyInspectionSeverity([$reportDevice])[0]['info']['check_meta']['result_items'][0]['labels'], '旧异常标记流程保留解析后的标签');
    echo "Completed {$checks} isolated checks. No business requests or writes.\n";
}
