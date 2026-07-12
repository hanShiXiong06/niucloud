<?php
declare(strict_types=1);

$root = dirname(__DIR__, 4);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);

$pc = $read('admin/src/addon/hsx_erp/components/counterparty-select/index.vue');
foreach (['editMemberDetail', "field: 'nickname'", '修改会员昵称', '@click.stop="editMemberNickname(item)"'] as $needle) {
    $assert(str_contains($pc, $needle), 'PC往来对象选择器缺少会员快捷改名：' . $needle);
}

$mobile = $read('site-uniapp/src/addon/hsx_erp/components/ErpPartyPopup.vue');
foreach (['editMemberField', 'saveMemberNickname', '改会员名', '不会修改 ERP 往来主体名称和历史单据'] as $needle) {
    $assert(str_contains($mobile, $needle), '移动端往来对象弹窗缺少会员快捷改名：' . $needle);
}

$memberApi = $read('site-uniapp/src/app/api/member.ts');
$assert(str_contains($memberApi, 'member/member/modify/${member_id}/${field}'), '移动端必须复用官方会员字段修改接口');

echo "[PASS] ERP member nickname shortcut smoke test\n";
