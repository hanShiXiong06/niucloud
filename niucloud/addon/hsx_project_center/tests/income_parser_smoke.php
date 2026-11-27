<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_project_center\app\service\core\ProjectCenterIncomeParserService;

$text = <<<'TEXT'
美团闪购 20260818商家收益排行
Top01: 宇通配件（城隍庙商城店）                收益 915.17
Top02: 志辉数码商城（环城西路店）              收益 526.01
无关说明不应该进入榜单
Top10：中国联通5Gn智慧生活馆（月湖街道店） 收益 441.22
TEXT;

$rows = (new ProjectCenterIncomeParserService())->parse($text);
if (count($rows) !== 3) throw new RuntimeException('收益榜应识别 3 条有效数据');
if ($rows[0]['rank_no'] !== 1 || $rows[0]['store_name'] !== '宇通配件（城隍庙商城店）') {
    throw new RuntimeException('榜单首行解析不正确');
}
if ((float)$rows[2]['income_amount'] !== 441.22) throw new RuntimeException('中文冒号或收益金额解析不正确');

echo "project center income parser smoke passed\n";
