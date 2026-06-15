<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpCapitalAccount;
use addon\hsx_erp\app\model\ErpCapitalLedger;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 资金账户 + 账目往来
 *
 * 账户：现金/微信/支付宝/银行卡，各记余额。
 * 账目往来：每笔收/付流水，记账即同步账户余额（事务+乐观一致）。
 */
class ErpCapitalAccountService extends BaseAdminService
{
    private array $accountTypes = ['cash' => '现金', 'wechat' => '微信', 'alipay' => '支付宝', 'bank' => '银行卡', 'other' => '其他'];

    public function accountTypeMap(): array
    {
        return $this->accountTypes;
    }

    /** 账户列表 */
    public function getAll(): array
    {
        $list = ErpCapitalAccount::where([['site_id', '=', $this->site_id]])
            ->order('is_default desc,sort asc,id asc')->select()->toArray();
        foreach ($list as &$row) {
            $row['account_type_text'] = $this->accountTypes[$row['account_type']] ?? $row['account_type'];
        }
        unset($row);
        return $list;
    }

    /** 新建/编辑账户 */
    public function save(array $data, int $id = 0): int
    {
        $name = trim((string)($data['account_name'] ?? ''));
        if ($name === '') {
            throw new CommonException('请填写账户名称');
        }
        $type = (string)($data['account_type'] ?? 'bank');
        if (!isset($this->accountTypes[$type])) {
            $type = 'bank';
        }
        $now = time();
        $isDefault = (int)($data['is_default'] ?? 0) === 1 ? 1 : 0;

        $values = [
            'account_name' => $name,
            'account_type' => $type,
            'bank_name'    => trim((string)($data['bank_name'] ?? '')),
            'account_no'   => trim((string)($data['account_no'] ?? '')),
            'holder'       => trim((string)($data['holder'] ?? '')),
            'currency'     => trim((string)($data['currency'] ?? 'CNY')) ?: 'CNY',
            'is_default'   => $isDefault,
            'status'       => (int)($data['status'] ?? 1) === 1 ? 1 : 0,
            'sort'         => (int)($data['sort'] ?? 0),
            'remark'       => trim((string)($data['remark'] ?? '')),
            'update_at'    => $now,
        ];

        return (int)Db::transaction(function () use ($id, $values, $now, $isDefault, $data) {
            if ($isDefault === 1) {
                ErpCapitalAccount::where([['site_id', '=', $this->site_id]])->update(['is_default' => 0, 'update_at' => $now]);
            }
            if ($id > 0) {
                $account = ErpCapitalAccount::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
                if ($account->isEmpty()) {
                    throw new CommonException('账户不存在');
                }
                $account->save($values);
                return $id;
            }
            // 新建：初始余额可选
            $values['site_id'] = $this->site_id;
            $values['balance'] = round((float)($data['balance'] ?? 0), 2);
            $values['create_at'] = $now;
            $account = ErpCapitalAccount::create($values);
            return (int)$account->id;
        });
    }

    /** 删除账户（有流水则禁止） */
    public function delete(int $id): bool
    {
        $count = ErpCapitalLedger::where([['site_id', '=', $this->site_id], ['account_id', '=', $id]])->count();
        if ($count > 0) {
            throw new CommonException('该账户已有流水，不能删除');
        }
        ErpCapitalAccount::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->delete();
        return true;
    }

    /** 手工记一笔收/付（同步余额），用于线下收付、调整等 */
    public function recordEntry(array $data): int
    {
        $accountId = (int)($data['account_id'] ?? 0);
        $direction = (string)($data['direction'] ?? 'in');
        $amount = round((float)($data['amount'] ?? 0), 2);
        if ($accountId <= 0) {
            throw new CommonException('请选择账户');
        }
        if (!in_array($direction, ['in', 'out'], true)) {
            throw new CommonException('收支方向不正确');
        }
        if ($amount <= 0) {
            throw new CommonException('金额必须大于0');
        }
        $now = time();

        return (int)Db::transaction(function () use ($accountId, $direction, $amount, $data, $now) {
            $account = ErpCapitalAccount::where([['site_id', '=', $this->site_id], ['id', '=', $accountId]])->lock(true)->findOrEmpty();
            if ($account->isEmpty()) {
                throw new CommonException('账户不存在');
            }
            $before = round((float)$account->balance, 2);
            // 出账校验：余额不足不允许出账（打款/支出等）；除非显式 allow_negative
            if ($direction === 'out' && empty($data['allow_negative']) && $before < $amount) {
                throw new CommonException(sprintf(
                    '账户「%s」余额不足：当前 %.2f，需出账 %.2f',
                    (string)$account->account_name, $before, $amount
                ));
            }
            $after = $direction === 'in' ? round($before + $amount, 2) : round($before - $amount, 2);
            $account->save(['balance' => $after, 'update_at' => $now]);

            $ledger = ErpCapitalLedger::create([
                'site_id'           => $this->site_id,
                'ledger_no'         => 'CAP' . date('YmdHis') . random_int(1000, 9999),
                'account_id'        => $accountId,
                'account_name'      => (string)$account->account_name,
                'direction'         => $direction,
                'amount'            => $amount,
                'balance_after'     => $after,
                'biz_type'          => (string)($data['biz_type'] ?? 'manual'),
                'counterparty_id'   => (int)($data['counterparty_id'] ?? 0),
                'counterparty_name' => (string)($data['counterparty_name'] ?? ''),
                'source_type'       => (string)($data['source_type'] ?? ''),
                'source_no'         => (string)($data['source_no'] ?? ''),
                'source_id'         => (int)($data['source_id'] ?? 0),
                'operator_uid'      => (int)$this->uid,
                'operator_name'     => (string)$this->username,
                'occurred_at'       => (int)($data['occurred_at'] ?? $now),
                'remark'            => (string)($data['remark'] ?? ''),
                'create_at'         => $now,
            ]);
            return (int)$ledger->id;
        });
    }

    /** 账目往来流水（可按账户/方向/对手方筛选） */
    /** 资金流水业务类型 → 中文 */
    public function bizTypeMap(): array
    {
        return [
            'manual'          => '手工',
            'recycle_payment' => '回收打款',
            'expense'         => '经营支出',
            'settlement'      => '结算',
            'sale'            => '销售收款',
            'buyout'          => '代卖买断',
            'transfer'        => '转账',
            'fee'             => '费用',
        ];
    }

    public function ledgerPage(array $where = []): array
    {
        $query = ErpCapitalLedger::where([['site_id', '=', $this->site_id]])->order('id desc');
        if (!empty($where['account_id'])) {
            $query->where('account_id', '=', (int)$where['account_id']);
        }
        if (!empty($where['direction'])) {
            $query->where('direction', '=', (string)$where['direction']);
        }
        if (!empty($where['biz_type'])) {
            $query->where('biz_type', '=', (string)$where['biz_type']);
        }
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->where('ledger_no|counterparty_name|source_no|remark', 'like', '%' . $kw . '%');
        }
        if (!empty($where['start_time'])) {
            $query->where('occurred_at', '>=', (int)$where['start_time']);
        }
        if (!empty($where['end_time'])) {
            $query->where('occurred_at', '<=', (int)$where['end_time']);
        }
        $page = $this->pageQuery($query);
        $map = $this->bizTypeMap();
        // 对手方精确到人：counterparty_id(=会员member_id)关联 member 表回填姓名+手机
        $memberMap = FinanceCounterpartyBalanceService::resolveMemberMap($this->site_id, array_column($page['data'] ?? [], 'counterparty_id'));
        foreach (($page['data'] ?? []) as &$row) {
            $bt = (string)($row['biz_type'] ?? '');
            $row['biz_type_text'] = $map[$bt] ?? ($bt !== '' ? $bt : '其它');
            $m = $memberMap[(int)($row['counterparty_id'] ?? 0)] ?? null;
            if ($m) {
                if ((string)($row['counterparty_name'] ?? '') === '') {
                    $row['counterparty_name'] = $m['name'];
                }
                $row['counterparty_mobile'] = $m['mobile'];
            }
        }
        unset($row);
        return $page;
    }
}
