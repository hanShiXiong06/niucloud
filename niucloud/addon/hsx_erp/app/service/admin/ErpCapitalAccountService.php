<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\FinanceDict;
use addon\hsx_erp\app\model\ErpCapitalAccount;
use addon\hsx_erp\app\model\ErpMoneyLedger;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class ErpCapitalAccountService extends BaseAdminService
{
    public const WECHAT_CLEARING_ACCOUNT_NO = 'system:phone_shop:wechat_clearing';
    public const SOURCE_PAID_CLEARING_ACCOUNT_NO = 'system:external_source:paid_reconciliation';

    public const TYPE_MAP = [
        'cash' => '现金',
        'wechat' => '微信',
        'alipay' => '支付宝',
        'bank' => '银行卡',
        'other' => '其他',
    ];

    public function lists(): array
    {
        $rows = ErpCapitalAccount::where([['site_id', '=', $this->site_id]])
            ->order('is_default desc, sort asc, id asc')
            ->select()
            ->toArray();
        foreach ($rows as &$row) {
            $row['account_type_text'] = self::TYPE_MAP[$row['account_type'] ?? ''] ?? '其他';
        }
        return $rows;
    }

    public function save(array $data, int $id = 0): int
    {
        $name = trim((string)($data['account_name'] ?? ''));
        if ($name === '') {
            throw new CommonException('请填写账户名称');
        }
        $now = time();
        $isDefault = (int)($data['is_default'] ?? 0) === 1 ? 1 : 0;
        if ($isDefault === 1) {
            ErpCapitalAccount::where([['site_id', '=', $this->site_id]])->update([
                'is_default' => 0,
                'update_at' => $now,
            ]);
        }
        $values = [
            'account_name' => $name,
            'account_type' => (string)($data['account_type'] ?? 'bank'),
            'bank_name' => trim((string)($data['bank_name'] ?? '')),
            'account_no' => trim((string)($data['account_no'] ?? '')),
            'holder' => trim((string)($data['holder'] ?? '')),
            'is_default' => $isDefault,
            'status' => (int)($data['status'] ?? 1) === 1 ? 1 : 0,
            'sort' => (int)($data['sort'] ?? 0),
            'remark' => trim((string)($data['remark'] ?? '')),
            'update_at' => $now,
        ];
        if ($id > 0) {
            $account = ErpCapitalAccount::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
            if ($account->isEmpty()) {
                throw new CommonException('资金账户不存在');
            }
            $account->save($values);
            return $id;
        }
        $row = ErpCapitalAccount::create(array_merge($values, [
            'site_id' => $this->site_id,
            'balance' => round((float)($data['balance'] ?? 0), 2),
            'create_at' => $now,
        ]));
        return (int)$row->id;
    }

    public function delete(int $id): bool
    {
        $account = $this->findAccount($id);
        if (round((float)$account->balance, 2) != 0.0) {
            throw new CommonException('账户余额不为0，不能删除，可先停用账户');
        }
        $hasLedger = ErpMoneyLedger::where([
            ['site_id', '=', $this->site_id],
            ['capital_account_id', '=', $id],
        ])->count();
        if ($hasLedger > 0) {
            throw new CommonException('账户已有流水，不能删除，可停用保留账目');
        }
        $account->delete();
        return true;
    }

    public function entry(array $data): int
    {
        $accountId = (int)($data['account_id'] ?? 0);
        $amount = round((float)($data['amount'] ?? 0), 2);
        $direction = (string)($data['direction'] ?? 'in');
        if (!in_array($direction, ['in', 'out'], true)) {
            throw new CommonException('记账方向错误');
        }
        if ($amount <= 0) {
            throw new CommonException('金额必须大于0');
        }
        $categoryKey = trim((string)($data['category_key'] ?? ''));
        $category = (new ErpConfigService())->findFinanceCategory($categoryKey);
        $expectedDirection = $direction === 'in' ? 'income' : 'expense';
        if (!$category || (string)($category['direction'] ?? '') !== $expectedDirection) {
            throw new CommonException($direction === 'in' ? '请选择有效的收入类型' : '请选择有效的支出类型');
        }
        if ((int)($category['party_required'] ?? 0) === 1 && (int)($data['party_id'] ?? 0) <= 0) {
            throw new CommonException('该收支类型必须选择往来主体');
        }
        $ledgerId = 0;
        Db::transaction(function () use ($accountId, $amount, $direction, $category, $data, &$ledgerId) {
            $account = $this->findAccount($accountId);
            $delta = $direction === 'in' ? $amount : -$amount;
            $balanceAfter = round((float)$account->balance + $delta, 2);
            $account->save([
                'balance' => $balanceAfter,
                'update_at' => time(),
            ]);
            $ledgerId = (new ErpLedgerService())->money([
                'capital_account_id' => (int)$account->id,
                'capital_account_name' => (string)$account->account_name,
                'direction' => $direction,
                'category_key' => (string)$category['key'],
                'category_name' => (string)$category['name'],
                'category_statement_group' => (string)($category['statement_group'] ?? ($direction === 'in' ? 'other_income' : 'other_expense')),
                'category_source_plugin' => (string)($category['source_plugin'] ?? ''),
                'category_source_key' => (string)($category['source_key'] ?? ''),
                'amount' => $amount,
                'party_id' => (int)($data['party_id'] ?? 0),
                'party_name' => trim((string)($data['counterparty_name'] ?? '')),
                'balance_after' => $balanceAfter,
                'voucher_urls' => $data['voucher_urls'] ?? '',
                'remark' => trim((string)($data['remark'] ?? '手工记账')),
            ]);
        });
        return $ledgerId;
    }

    public function ledger(array $where): array
    {
        $query = ErpMoneyLedger::where([['site_id', '=', $this->site_id]]);
        if (!empty($where['account_id'])) {
            $query->where('capital_account_id', '=', (int)$where['account_id']);
        }
        if (!empty($where['direction'])) {
            $query->where('direction', '=', (string)$where['direction']);
        }
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('ledger_no|party_name|capital_account_name|category_name|remark', '%' . $kw . '%');
        }
        if (!empty($where['start_time'])) {
            $query->where('occurred_at', '>=', (int)$where['start_time']);
        }
        if (!empty($where['end_time'])) {
            $query->where('occurred_at', '<=', (int)$where['end_time']);
        }
        return $query->order('id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
    }

    public function typeMap(): array
    {
        return self::TYPE_MAP;
    }

    /**
     * 获取商城线上支付清算账户。
     *
     * 该账户表达“支付渠道已收、尚未提现到银行卡”的真实资金位置，商城普通
     * 商品与 ERP 一物一码设备必须共用，不能因来源不同生成两套资金口径。
     */
    public static function ensureWechatClearingAccount(int $siteId): ErpCapitalAccount
    {
        if ($siteId <= 0) throw new CommonException('线上支付缺少有效站点');
        $account = ErpCapitalAccount::where([
            ['site_id', '=', $siteId],
            ['account_no', '=', self::WECHAT_CLEARING_ACCOUNT_NO],
        ])->findOrEmpty();
        if (!$account->isEmpty()) return $account;

        $now = time();
        return ErpCapitalAccount::create([
            'site_id' => $siteId,
            'account_name' => '微信支付待结算',
            'account_type' => 'wechat',
            'bank_name' => '微信支付',
            'account_no' => self::WECHAT_CLEARING_ACCOUNT_NO,
            'holder' => '',
            'balance' => 0,
            'is_default' => 0,
            'status' => 1,
            'sort' => 900,
            'remark' => '系统清算账户：商城线上支付、渠道手续费及退款自动留痕',
            'create_at' => $now,
            'update_at' => $now,
        ]);
    }

    /**
     * 获取“来源系统已付”核对账户。
     *
     * 适用于 ERP 安装前已由回收等来源系统完成付款的历史事实。把对应应付
     * 当场核销，能够阻止财务重复付款；账户余额保留为负数，明确提示财务
     * 后续把真实银行/微信流水调拨到该核对账户，而不是伪造一笔新付款。
     */
    public static function ensureSourcePaidClearingAccount(int $siteId): ErpCapitalAccount
    {
        if ($siteId <= 0) throw new CommonException('来源已付核对缺少有效站点');
        $account = ErpCapitalAccount::where([
            ['site_id', '=', $siteId],
            ['account_no', '=', self::SOURCE_PAID_CLEARING_ACCOUNT_NO],
        ])->findOrEmpty();
        if (!$account->isEmpty()) return $account;

        $now = time();
        return ErpCapitalAccount::create([
            'site_id' => $siteId,
            'account_name' => '来源系统已付待核对',
            'account_type' => 'other',
            'bank_name' => '来源业务系统',
            'account_no' => self::SOURCE_PAID_CLEARING_ACCOUNT_NO,
            'holder' => '',
            'balance' => 0,
            'is_default' => 0,
            'status' => 1,
            'sort' => 910,
            'remark' => '系统核对账户：记录ERP接入前或来源系统已完成的付款，防止重复打款；请按真实流水完成账户调拨核对',
            'create_at' => $now,
            'update_at' => $now,
        ]);
    }

    /** 兼容既有调用；实际字典由 FinanceDict 统一维护。 */
    public static function bizTypeMap(): array
    {
        return FinanceDict::getBizTypeMap();
    }

    private function findAccount(int $id): ErpCapitalAccount
    {
        $account = ErpCapitalAccount::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($account->isEmpty()) {
            throw new CommonException('资金账户不存在');
        }
        return $account;
    }
}
