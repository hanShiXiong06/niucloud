<?php
declare(strict_types=1);

// 手工覆盖升级：只注册本功能的计划任务，不建表、不修改报价/订单或其他任务。
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (in_array('--help', $argv, true)) {
    echo "用法：php addon/hsx_recycle/scripts/register_quote_image_sync.php [--apply]\n";
    echo "默认只读预览。--apply 仅补充报价图片采集的每5分钟检查任务，保留已有启停设置。\n";
    echo "脚本本身不抓图；只有站点保存映射并开启自动采集后，计划任务和队列消费者才会执行。\n";
    exit;
}
$apply = in_array('--apply', $argv, true);
require dirname(__DIR__, 3) . '/vendor/autoload.php';
(new \think\App(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR))->initialize();
$rows = array_values(array_filter(require dirname(__DIR__) . '/app/dict/schedule/schedule.php',
    static fn(array $row): bool => $row['key'] === 'hsx_recycle_quote_image_sync'));
if (count($rows) !== 1) throw new \RuntimeException('缺少任务字典，请先上传完整插件文件');
$exists = \think\facade\Db::name('sys_schedule')->where('addon', 'hsx_recycle')->where('key', $rows[0]['key'])->find();
echo $exists ? "任务已存在，保留当前配置。\n" : "待注册：报价图片每日自动采集（每5分钟检查本站设定时间）。\n";
if (!$exists && $apply) {
    (new \app\service\core\schedule\CoreScheduleInstallService())->install($rows, 'hsx_recycle');
    echo "已注册。请按现有部署方式重载计划任务进程并检查队列消费者。\n";
} elseif (!$apply) echo "只读预览；确认后加 --apply 执行。\n";
echo "未创建新表/字段，未直接抓取或修改报价。\n";
