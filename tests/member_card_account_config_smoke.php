<?php
declare(strict_types=1);

// 执行真实配置服务，存储与外部 ERP 能力在内存隔离；不连接数据库，不发送财务事件。
namespace core\base {
    class BaseAdminService { public int $site_id = 1; }
}
namespace core\exception {
    class CommonException extends \RuntimeException {}
}
namespace app\service\core\sys {
    class CoreConfigService {
        public static array $memory = [];
        public function getConfigValue(int $siteId, string $key): array { return self::$memory[$siteId][$key] ?? []; }
        public function setConfig(int $siteId, string $key, array $value): void { self::$memory[$siteId][$key] = $value; }
    }
}
namespace addon\hsx_member_card\app\service\admin {
    class MemberCardFinanceGateway {
        public static bool $erp = false;
        public static array $accounts = [];
        public function usesErp(): bool { return self::$erp; }
        public function capitalAccountOptions(): array {
            if (self::$erp) return self::$accounts;
            $config = (new \addon\hsx_member_card\app\service\core\MemberCardConfigService())->get(1);
            return array_values(array_filter($config['local_capital_accounts'], static fn(array $row): bool => $row['status'] === 1));
        }
    }
    class MemberCardInventoryGateway {
        public function capability(): array { return ['available' => 0]; }
    }
}
namespace {
    require dirname(__DIR__) . '/niucloud/addon/hsx_member_card/app/service/core/MemberCardConfigService.php';
    require dirname(__DIR__) . '/niucloud/addon/hsx_member_card/app/service/admin/MemberCardConfigAdminService.php';
    use addon\hsx_member_card\app\service\admin\MemberCardConfigAdminService;
    use addon\hsx_member_card\app\service\admin\MemberCardFinanceGateway;
    use addon\hsx_member_card\app\service\core\MemberCardConfigService;

    $count = 0;
    $check = static function (bool $condition, string $name) use (&$count): void {
        if (!$condition) throw new \RuntimeException('FAIL ' . $name);
        $count++;
        echo "PASS {$name}\n";
    };
    $find = static function (array $info, int $id): array {
        foreach ($info['capital_account_options'] as $row) if ($row['id'] === $id) return $row;
        return [];
    };
    $service = new MemberCardConfigAdminService();
    $info = $service->info();
    $check($info['finance_provider'] === 'local' && $info['allow_receivable'] === 0, '未接 ERP 可以独立使用，只登记实际收款');
    $check(count($info['capital_account_options']) === 4, '独立运行默认提供四类账户');
    $info = $service->saveCapitalAccount(['name' => '验收专用账户', 'type' => 'cash', 'status' => 1, 'is_default' => 1]);
    $id = $info['default_capital_account_id'];
    $check($find($info, $id)['name'] === '验收专用账户', '独立账户可新增并设为默认');
    $info = $service->saveCapitalAccount(['id' => $id, 'name' => '已编辑测试账户', 'type' => 'bank', 'status' => 1, 'is_default' => 1]);
    $check($find($info, $id)['type'] === 'bank' && count($info['capital_account_options']) === 5, '编辑复用原账户，不新增副本');
    $info = $service->save(['default_capital_account_id' => 2]);
    $check($info['default_capital_account_id'] === 2 && $find($info, 2)['is_default'] === 1 && $find($info, $id)['is_default'] === 0, '默认账户配置与列表标识一致');
    $info = $service->saveCapitalAccount(['id' => 2, 'name' => '停用账户', 'type' => 'alipay', 'status' => 0, 'is_default' => 1]);
    $check($info['default_capital_account_id'] !== 2 && $find($info, 2)['is_default'] === 0, '停用账户不能继续成为默认，包括异常前端参数');
    $oldDefault = $info['default_capital_account_id'];
    $info = $service->deleteCapitalAccount($oldDefault);
    $check($find($info, $oldDefault) === [] && $info['default_capital_account_id'] !== $oldDefault, '删除当前默认账户后重新选取可用账户');
    $rejected = false;
    try { $service->save(['default_capital_account_id' => 2]); } catch (\core\exception\CommonException $e) { $rejected = true; }
    $check($rejected, '后台拒绝将已停用账户设为默认');
    foreach ($info['capital_account_options'] as $row) $info = $service->saveCapitalAccount(array_replace($row, ['status' => 0]));
    $check($info['default_capital_account_id'] === 0, '所有账户停用后无默认账户，不冒充可用');
    foreach ($info['capital_account_options'] as $row) $info = $service->deleteCapitalAccount($row['id']);
    $check($info['capital_account_options'] === [], '清空测试账户后保留空配置，不自动恢复旧账户');

    MemberCardFinanceGateway::$erp = true;
    MemberCardFinanceGateway::$accounts = [
        ['id' => 71, 'name' => 'ERP 账户甲', 'is_default' => 1],
        ['id' => 72, 'name' => 'ERP 账户乙', 'is_default' => 0],
    ];
    $info = $service->save(['default_capital_account_id' => 72]);
    $check($info['default_capital_account_id'] === 72, 'ERP 默认账户不再被独立账户 ID 校验覆盖');
    $info = $service->info();
    $check($info['default_capital_account_id'] === 72 && $find($info, 72)['is_default'] === 1, '再次读取仍保留 ERP 默认选择');
    $check((new MemberCardConfigService())->get(1)['default_capital_account_id'] === 72, 'ERP 默认选择已持久化到配置层');
    $rejected = false;
    try { $service->saveCapitalAccount(['name' => '不应新增']); } catch (\core\exception\CommonException $e) { $rejected = true; }
    $check($rejected, 'ERP 托管时禁止在会员卡里新建另一套收款账户');
    $other = new MemberCardConfigAdminService();
    $other->site_id = 2;
    MemberCardFinanceGateway::$erp = false;
    $check(count($other->info()['capital_account_options']) === 4, '测试站点配置不会修改其他站点');
    echo "\n{$count} PASS — 内存测试，无 SQL / 财务写入\n";
}
