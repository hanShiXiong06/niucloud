<?php
declare(strict_types=1);

$addonRoot = dirname(__DIR__);
$projectRoot = dirname($addonRoot, 3);
$read = static fn(string $path): string => (string)file_get_contents($path);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$install = $read($addonRoot . '/sql/install.sql');
$uninstall = $read($addonRoot . '/sql/uninstall.sql');
$schema = $read($addonRoot . '/app/support/ErpSchema.php');
$service = $read($addonRoot . '/app/service/admin/ErpOpeningService.php');
$job = $read($addonRoot . '/app/job/ErpOpeningImport.php');
$controller = $read($addonRoot . '/app/adminapi/controller/ErpOpening.php');
$route = $read($addonRoot . '/app/adminapi/route/route.php');
$menu = $read($addonRoot . '/app/dict/menu/site.php');
$register = $read($projectRoot . '/niucloud/app/service/api/login/RegisterService.php');
$pc = $read($projectRoot . '/admin/src/addon/hsx_erp/views/erp/opening/index.vue');
$addonPc = $read($addonRoot . '/admin/views/erp/opening/index.vue');

foreach (['erp_opening_batch', 'erp_opening_item'] as $table) {
    $assert(str_contains($install, "CREATE TABLE IF NOT EXISTS `{{prefix}}{$table}`"), "安装脚本缺少{$table}");
    $assert(str_contains($uninstall, "DROP TABLE IF EXISTS `{{prefix}}{$table}`"), "卸载脚本缺少{$table}");
    $assert(str_contains($schema, $table), "运行时数据库迁移缺少{$table}");
}
foreach ([
    'member_created_count',
    'member_reused_count',
    'party_created_count',
    'party_reused_count',
    'summary_json',
    'result_json',
] as $column) {
    $assert(str_contains($install, "`{$column}`"), "期初建账批次缺少{$column}留痕");
}

foreach ([
    'opening/upload',
    'opening/lists',
    'opening/:id',
    'opening/:id/items',
    'opening/:id/retry',
    'opening/:id/confirm',
] as $uri) {
    $assert(str_contains($route, $uri), "缺少期初建账接口：{$uri}");
}
$assert(str_contains($menu, "'menu_name' => '期初建账'"), '缺少期初建账菜单');
$assert(str_contains($controller, "param('opening_date'"), '上传时必须支持指定建账日期');
$assert(str_contains($job, "string \$action = 'parse'"), '后台任务必须区分校验与正式入账');

foreach (['客户资料', '设备库存', '应收余额', '应付余额', '资金账户'] as $sheet) {
    $assert(str_contains($service, "'{$sheet}'"), "后端缺少{$sheet}解析规则");
    $assert(str_contains($pc, "'{$sheet}'"), "完整模板缺少{$sheet}工作表");
}
$assert(str_contains($pc, '填写示例（请勿导入）'), '模板必须包含独立示例页，避免示例被误导入');
$assert(str_contains($pc, '使用说明'), '模板必须包含完整填写说明');
$assert(str_contains($pc, '下载完整模板'), '页面必须提供模板下载入口');
$assert(str_contains($pc, '下载错误明细'), '页面必须提供错误明细下载入口');
$assert(str_contains($pc, '导出账号处理结果'), '页面必须提供账号归并结果导出入口');
$assert(str_contains($pc, '确认后才正式入账'), '页面必须明确上传不等于正式入账');

$assert(str_contains($service, "private const DEFAULT_PASSWORD = '123456'"), '新账号初始密码必须为123456');
$assert(str_contains($service, "'username' => \$mobile"), '新账号用户名必须使用手机号');
$assert(str_contains($service, "'mobile' => \$mobile"), '新账号必须保存手机号');
$assert(str_contains($service, 'create_password(self::DEFAULT_PASSWORD)'), '新账号密码必须使用框架密码方法加密');
$assert(str_contains($service, 'CoreMemberService::setMemberNo'), '新账号必须生成框架会员编号');
$assert(str_contains($service, "event('MemberRegister'"), '新账号创建后必须触发框架会员注册事件');
$assert(str_contains($service, "'initial_password' => self::DEFAULT_PASSWORD"), '结果留痕必须返回新账号初始密码');
$assert(str_contains($register, "MemberLoginTypeDict::WEAPP => 'weapp_openid'"), '框架微信授权登录必须能定位weapp_openid');
$assert(str_contains($register, "findMemberInfo([ 'mobile' => \$mobile"), '框架微信授权登录必须先按手机号查找已有账号');
$assert(str_contains($register, '$member->save();'), '框架微信授权登录必须能把openid写回已有手机号账号');

$assert(str_contains($service, "['mobile', '=', \$mobile]"), '账号一致性必须优先按手机号查找');
$assert(str_contains($service, '对应多个会员账号，请先人工合并'), '同手机号多会员必须拦截并提示人工合并');
$assert(str_contains($service, '对应多个往来主体，请先人工合并'), '同手机号多主体必须拦截并提示人工合并');
$assert(str_contains($service, '关联了多个有效往来主体，请先人工合并'), '会员多主体关系必须拦截');
$assert(str_contains($service, "'relation_role' => 'business'"), '会员与ERP往来主体必须形成明确关联');
$assert(str_contains($service, 'consolidated_row_count'), '结果必须统计被手机号归并的来源行数');
$assert(str_contains($service, "'new_accounts' => []"), '结果必须列出新建账号');
$assert(str_contains($service, "'matched_accounts' => []"), '结果必须列出复用账号');
$assert(str_contains($service, "'source_rows'"), '账号结果必须保留来源工作表和行号');
$assert(str_contains($service, "'name_warnings' => \$nameWarnings"), '姓名差异必须形成可见的核对提示');
$assert(str_contains($pc, '账号现有昵称'), '正式结果必须并列展示导入姓名和账号现有昵称');

$assert(str_contains($service, "'status' => \$errors > 0 ? 'invalid' : 'ready'"), '校验结果必须明确区分待确认与不可入账状态');
$assert(str_contains($service, '仍有校验错误，请下载或查看错误明细'), '存在错误时必须阻止确认入账');
$assert(str_contains($service, 'Db::transaction'), '正式入账必须使用数据库事务');
$assert(str_contains($service, '业务事务已回滚'), '入账异常必须明确告知已回滚');
$assert(str_contains($service, '已入账批次不能删除，应保留审计留痕'), '已入账批次必须保留审计记录');
$assert(str_contains($service, "'action' => 'opening_posted'"), '确认入账必须写操作日志');
$assert(str_contains($service, '商品目录型号不存在或已停用'), '无效商品目录型号必须在入账前拦截');

$postCapitalStart = strpos($service, 'private function postCapital(');
$resolveIdentityStart = strpos($service, 'private function resolveIdentity(');
$assert($postCapitalStart !== false && $resolveIdentityStart !== false, '无法定位资金期初入账实现');
$postCapital = substr($service, $postCapitalStart, $resolveIdentityStart - $postCapitalStart);
$assert(str_contains($postCapital, "'balance' => round((float)\$data['balance'], 2)"), '资金账户必须直接写入明确的期初余额');
$assert(!str_contains($postCapital, 'ErpMoneyLedger'), '资金期初余额不能伪装成真实收付款流水');

$assert($pc === $addonPc, '发布源与插件内PC期初建账页面必须保持一致');

echo "[PASS] ERP opening account import smoke test\n";
