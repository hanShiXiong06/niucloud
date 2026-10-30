<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use app\model\member\Member;
use app\service\admin\auth\AuthService;
use app\service\admin\sys\RoleService;
use core\base\BaseAdminService;
use core\exception\AuthException;
use core\exception\CommonException;

/** 独立查询客户当前保存的收款资料，不创建往来主体、订单或资金记录。 */
class ErpPayeeLookupService extends BaseAdminService
{
    public function customers(array $params): array
    {
        $this->assertCanLookup();
        $keyword = trim((string)($params['keyword'] ?? ''));
        if ($keyword === '') throw new CommonException('请输入客户姓名、手机号或会员编号');
        if (mb_strlen($keyword) > 100) throw new CommonException('查询条件不能超过100个字符');

        $page = $this->customerQuery()->withSearch(['keyword'], ['keyword' => $keyword])
            ->field('member_id,member_no,nickname,username,mobile')->order('member_id desc')
            ->paginate([
                'list_rows' => max(1, min(20, (int)($params['limit'] ?? 10))),
                'page' => max(1, (int)($params['page'] ?? 1)),
            ])->toArray();
        $page['data'] = array_map([$this, 'customerInfo'], $page['data']);
        return $page;
    }

    public function customer(int $memberId): array
    {
        $this->assertCanLookup();
        $member = $this->customerQuery()->where('member_id', $memberId)
            ->field('member_id,member_no,nickname,username,mobile')->findOrEmpty();
        if ($member->isEmpty()) throw new CommonException('客户不存在或已停用，请重新查询');

        try {
            $responses = array_values(array_filter((array)event('GetRecyclePaymentMethods', [
                'site_id' => (int)$this->site_id, 'member_ids' => [$memberId],
            ]), 'is_array'));
        } catch (\Throwable $e) {
            throw new CommonException('收款资料读取失败，请稍后重试；仍失败请联系管理员');
        }
        $methods = [];
        foreach ($responses as $response) {
            foreach ((array)($response[$memberId] ?? []) as $item) {
                if (!is_array($item)) continue;
                $method = [];
                foreach (['pay_type', 'account', 'qrcode_image'] as $key) {
                    $method[$key] = is_scalar($item[$key] ?? null) ? trim((string)$item[$key]) : '';
                }
                if ($method['account'] === '' && $method['qrcode_image'] === '') continue;
                $method['is_default'] = (int)($item['is_default'] ?? 0) === 1 ? 1 : 0;
                $key = json_encode([$method['pay_type'], $method['account'], $method['qrcode_image']]);
                if (!isset($methods[$key]) || $method['is_default']) $methods[$key] = $method;
            }
        }
        $methods = array_values($methods);
        usort($methods, static fn(array $a, array $b): int => $b['is_default'] <=> $a['is_default']);
        return [
            'customer' => $this->customerInfo($member->toArray()),
            'available' => $responses !== [],
            'methods' => $methods,
        ];
    }

    protected function assertCanLookup(): void
    {
        // 菜单权限外再校验财务岗位，避免菜单缓存尚未更新时出现权限空档。
        if ((int)$this->site_id <= 0 || (int)$this->uid <= 0) throw new AuthException('NO_PERMISSION');
        $auth = new AuthService();
        $relation = (array)($auth->getAuthRole((int)$this->site_id) ?? []);
        if (AuthService::isSuperAdmin()) return;
        if ((int)($relation['status'] ?? 0) !== 1) throw new AuthException('NO_PERMISSION');
        if ((int)($relation['is_admin'] ?? 0) === 1) return;
        $roleIds = array_values(array_filter(array_map('intval', (array)($relation['role_ids'] ?? []))));
        $rules = $roleIds === [] ? [] : (new RoleService())->getMenuIdsByRoleIds((int)$this->site_id, $roleIds);
        if (!in_array('hsx_erp_payable', $rules, true)) throw new AuthException('NO_PERMISSION');
    }

    private function customerQuery()
    {
        return Member::where([['site_id', '=', (int)$this->site_id], ['is_del', '=', 0], ['status', '=', 1]]);
    }

    private function customerInfo(array $row): array
    {
        return [
            'member_id' => (int)$row['member_id'],
            'name' => trim((string)$row['nickname']) ?: (trim((string)$row['username']) ?: '未设置姓名'),
            'member_no' => (string)$row['member_no'],
            'mobile' => (string)$row['mobile'],
        ];
    }
}
