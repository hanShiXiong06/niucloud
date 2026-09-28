<?php
declare(strict_types=1);

// 仅验证校验器和写入前闸门；不加载 .env，不连接业务数据库。
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
}
namespace core\base {
    class BaseAdminService { public int $site_id = 100005; public int $uid = 9; public function __construct() {} }
}
namespace {
    use addon\hsx_recycle\app\service\core\recycle_order\DeviceEntryImei;
    use addon\hsx_recycle\app\service\core\recycle_order\handler\SignHandler;
    use addon\hsx_recycle\app\service\admin\order\RecycleOrderDeviceService;
    use addon\hsx_recycle\app\service\admin\order\RecycleOrderService;
    use addon\hsx_recycle\app\service\admin\order\RecycleDeviceService;
    use addon\hsx_recycle\app\validate\RecycleOrderValidate;
    use addon\hsx_recycle\app\validate\RecycleDeviceValidate;
    $checks = 0;
    function check($expected, $actual, string $name): void {
        if ($expected !== $actual) throw new \RuntimeException('FAIL ' . $name . ': ' . json_encode([$expected, $actual], JSON_UNESCAPED_UNICODE));
        $GLOBALS['checks']++; echo 'PASS ' . $name . PHP_EOL;
    }
    function blocked(callable $run, string $fragment, string $name): void {
        try { $run(); } catch (\core\exception\CommonException $e) { check(true, str_contains($e->getMessage(), $fragment), $name); return; }
        throw new \RuntimeException('FAIL 未拦截：' . $name);
    }
    function bare(string $class): object { return (new \ReflectionClass($class))->newInstanceWithoutConstructor(); }
    try {
        foreach (['123456', '000001', 'AbC123', 'abcdefghijklmno', '353938205192374', 123456] as $value) {
            check('', DeviceEntryImei::error($value), '允许普通串号 ' . $value);
        }
        $invalid = ['', null, '12345', ' 123456', '123456 ', '123 456', "123456\n", "123\t456", 'ABC-123', 'ABC_123', '１２３４５６', '中文123456', 'ABC#123', '1234567890123456', [], true, 1.23456];
        foreach ($invalid as $i => $value) {
            check(true, DeviceEntryImei::error($value) !== '', '拒绝非法串号 #' . $i);
            foreach ([new RecycleOrderValidate(), new RecycleDeviceValidate()] as $validator) {
                $validator->setLang(bare(\think\Lang::class));
                $scene = $validator instanceof RecycleOrderValidate ? 'addDevice' : 'imei';
                check(false, $validator->scene($scene)->check(['id' => 1, 'imei' => $value, 'category_id' => 1]), '接口校验同步拦截 #' . $i . ' ' . $scene);
            }
        }
        check(true, (new RecycleOrderValidate())->scene('addDevice')->check(['id' => 1, 'imei' => 'Abc123', 'category_id' => 1]), '新增接口接受字母串号');
        check(true, (new RecycleDeviceValidate())->scene('imei')->check(['imei' => '000001']), '修改接口保留前导零');
        DeviceEntryImei::assertDevices([]);
        check(true, true, '允许尚未录设备的代下单草稿');
        blocked(fn() => bare(RecycleOrderDeviceService::class)->addDeviceToOrder(1, ['imei' => '']), '请填写', '新增设备在任何数据库调用前拦截');
        blocked(fn() => bare(RecycleOrderDeviceService::class)->batchAddDevicesToOrder(1, [['imei' => 'ABC123'], ['imei' => 'A B123']]), '第 2 台设备', '批量第二台无效时第一台也不开始写入');
        blocked(fn() => bare(RecycleOrderService::class)->create(['devices' => [['imei' => '12345']]]), '至少填写 6 位', '代下单在创建订单前拦截');
        blocked(fn() => bare(RecycleDeviceService::class)->update(1, ['imei' => '']), '请填写', '保存修改不允许清空串号');
        blocked(fn() => bare(RecycleDeviceService::class)->update(1, ['imei' => null]), '请填写', '显式null不能绕过更新校验');
        blocked(fn() => (new SignHandler())->handle(['id' => 1, 'member_id' => 1], ['devices' => [['id' => 1, 'imei' => 'ABC123'], ['id' => 2, 'imei' => '']]], ['site_id' => 100005]), '第 2 台设备', '最终签收逐台校验且整批写入前失败');
        blocked(fn() => (new SignHandler())->handle(['id' => 1, 'member_id' => 1], ['devices' => ['imei' => '12345']], ['site_id' => 100005]), '至少填写 6 位', '单台关联数组签收同样校验');
        echo "完成：{$checks} 项检查；未访问数据库。\n";
    } catch (\Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n" . $e->getTraceAsString() . "\n"); exit(1); }
}
