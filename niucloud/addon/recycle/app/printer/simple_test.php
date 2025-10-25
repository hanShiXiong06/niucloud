<?php
// +----------------------------------------------------------------------
// | 打印机简单测试脚本
// +----------------------------------------------------------------------

namespace addon\recycle\app\printer;

require_once __DIR__ . '/TestPrinter.php';
require_once __DIR__ . '/PrinterLib/model/RestRequest.php';
require_once __DIR__ . '/PrinterLib/model/XPYunResp.php';
require_once __DIR__ . '/PrinterLib/service/PrintService.php';
require_once __DIR__ . '/PrinterLib/service/HttpClient.php';

echo "=== 芯烨云打印机测试 ===\n\n";

// 实例化测试类（可以自定义账号信息）
$printer = new TestPrinter();

// 定义测试数据
$testData = [
    'order_no' => date('YmdHis'),
    'category_name' => '手机',
    'brand' => 'iPhone',
    'model' => '13 Pro',
    'color' => '银色',
    'memory' => '8GB',
    'capacity' => '128GB',
    'imei' => '123456789012345',
    'sn' => 'ABCDEF123456',
    'check_result' => '质检通过',
    'final_price' => '4000',
    'weight' => '0.4',
    'time' => date('Y-m-d H:i:s')
];

// 测试标签打印
echo "\n=== 开始测试标签打印 ===\n";
$result = $printer->testLabelPrint($testData);

echo "\n\n=== 测试完成 ===\n";
echo "如果返回状态码为200且返回结果中的code为0，则表示打印成功\n";
echo "如果打印失败，请查看返回结果中的msg字段以了解失败原因\n"; 