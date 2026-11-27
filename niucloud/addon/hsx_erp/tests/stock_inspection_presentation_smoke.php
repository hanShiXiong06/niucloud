<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/support/ErpInspectionPresentation.php';
require dirname(__DIR__) . '/app/support/ErpStockCostPresentation.php';
use addon\hsx_erp\app\support\ErpInspectionPresentation as Inspection;
use addon\hsx_erp\app\support\ErpStockCostPresentation as Cost;
$count = 0;
$assert = static function (bool $ok, string $message) use (&$count): void { $count++; if (!$ok) throw new RuntimeException($message); };
$items = [
    ['field_key'=>'battery','field_name'=>'电池健康度','labels'=>['100'],'text'=>'电池健康度100%','severity'=>'normal'],
    ['field_key'=>'screen','field_name'=>'屏幕','text'=>'屏幕：有细微划痕','severity'=>'general'],
    ['field_key'=>'camera','field_name'=>'摄像头','labels'=>['正常'],'text'=>'摄像头: 正常','severity'=>'normal'],
];
$rawText = implode('; ', array_column($items, 'text'));
$base = ['qc_report'=>json_encode(['report'=>['result_items'=>$items], 'raw'=>['check_remark'=>'']]),
    'quality_remark'=>mb_substr($rawText,0,32), 'remark'=>'来源设备#6', 'remark_internal'=>'来源插件 hsx_recycle；来源设备ID 6'];
$view = Inspection::fromAsset($base);
$assert($view['count'] === 3, '结构化项目数');
$assert($view['items'][0]['value'] === '100%', '保留数值单位');
$assert($view['items'][1]['value'] === '有细微划痕', '中文冒号无乱码');
$assert($view['counts']['normal'] === 2 && $view['counts']['general'] === 1, '按快照级别统计，不自行推断');
$assert($view['manual_notes'] === [], '旧版截断的自动质检摘要和内部编号不充当人工备注');
$view = Inspection::fromAsset(array_replace($base,['quality_remark'=>'客户要求保留旧电池','remark'=>'下午联系客户','remark_internal'=>'已与客户确认']));
$assert(count($view['manual_notes']) === 3, '不同人工说明保留');
$view = Inspection::fromAsset(['qc_report'=>['raw'=>['human_remark'=>str_repeat('人工作业说明',120)]], 'quality_remark'=>str_repeat('人工作业说明',2)]);
$assert(count($view['manual_notes']) === 1 && mb_strlen($view['manual_notes'][0]['text']) > 500, '人工原文不截断、不重复显示500字摘录');
$view = Inspection::fromAsset(['qc_report'=>['raw'=>['check_result_buyer'=>json_encode(['result_items'=>$items])]]]);
$assert($view['count'] === 3, '兼容旧快照结果字段');
$items[0]['severity']='unrecognized';
$view = Inspection::fromAsset(['qc_report'=>['report'=>['result_items'=>$items]]]);
$assert($view['counts']['unknown'] === 1, '未知级别不当正常');
foreach (['bad JSON', '', null, 123, []] as $value) {
    $view = Inspection::fromAsset(['qc_report'=>$value,'remark'=>'保留人工备注']);
    $assert($view['count'] === 0 && $view['manual_notes'][0]['text'] === '保留人工备注','损坏快照不能令档案崩溃');
}
$view = Inspection::fromAsset(['qc_report'=>['raw'=>['check_result'=>'历史原始质检字符串']]]);
$assert($view['legacy_text'] === '历史原始质检字符串','缺少结构化数据保留历史原文');
$s = Cost::summarize(['purchase_cost'=>4500,'refurbish_cost'=>0,'total_cost'=>4700],4600);
$assert($s['supplier_adjust_cost'] === 100.0 && $s['internal_adjust_cost'] === 100.0,'样本4700成本与4600采购款拆分');
$s = Cost::summarize(['purchase_cost'=>4500,'refurbish_cost'=>100,'total_cost'=>4600],4500);
$assert($s['refurbish_cost'] === 100.0 && $s['supplier_amount'] === 4500.0 && $s['internal_adjust_cost'] === 0.0,'维修100不算原供应商货款');
$s = Cost::summarize(['purchase_cost'=>4500,'refurbish_cost'=>100,'total_cost'=>4580],4500);
$assert($s['internal_adjust_cost'] === -20.0,'保留负向账面修正');
$service = file_get_contents(dirname(__DIR__).'/app/service/admin/ErpStockService.php');
$assert(substr_count($service, 'ErpStockCostPresentation::summarize(') === 2,'列表和详情统一成本公式');
$visibility = file_get_contents(dirname(__DIR__).'/app/service/admin/ErpDataVisibilityService.php');
$assert(str_contains(substr($visibility,strpos($visibility,'private function sanitizeStockRow')), "'total_cost', 'cost_summary'"),'新增汇总必须受后端成本权限保护');
echo "PASS stock inspection/cost presentation: {$count} assertions.\n";
