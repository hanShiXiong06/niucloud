<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_erp\app\support\ErpListingWorkflow;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected === $actual) return;
    fwrite(STDERR, sprintf("FAIL: %s\nexpected: %s\nactual:   %s\n", $message, var_export($expected, true), var_export($actual, true)));
    exit(1);
}

$basePolicy = ['can_prepare_mall' => 1, 'can_list_mall' => 0];

assertSameValue('need_photo', ErpListingWorkflow::statusFromAsset([
    'image_urls' => '', 'retail_price' => 0,
], $basePolicy), '商城设备首先进入商品拍摄');

assertSameValue('need_price', ErpListingWorkflow::statusFromAsset([
    'image_urls' => '["/a.jpg"]', 'retail_price' => 0,
], $basePolicy), '图片完成后进入销售定价');

assertSameValue('need_material', ErpListingWorkflow::statusFromAsset([
    'image_urls' => ['/a.jpg'], 'retail_price' => 1999,
], $basePolicy), '图片和价格完成后进入资料整理');

assertSameValue('ready', ErpListingWorkflow::statusFromAsset([
    'image_urls' => '/a.jpg,/b.jpg', 'retail_price' => 1999,
], ['can_prepare_mall' => 1, 'can_list_mall' => 1]), '完整商品资料可直接上架');

assertSameValue(false, ErpListingWorkflow::canHandoffToShop([
    'image_urls' => [], 'retail_price' => 1999,
], $basePolicy), '没有图片不能交接商城运营');

assertSameValue(true, ErpListingWorkflow::canHandoffToShop([
    'image_urls' => '["/a.jpg"]', 'retail_price' => 1999,
], $basePolicy), '图片和价格完成后可交接商城运营');

assertSameValue('none', ErpListingWorkflow::statusFromAsset([
    'image_urls' => '["/a.jpg"]', 'retail_price' => 1999,
], ['can_prepare_mall' => 0, 'can_list_mall' => 0]), '非商城仓不产生上架待办');

fwrite(STDOUT, "PASS listing workflow smoke\n");
