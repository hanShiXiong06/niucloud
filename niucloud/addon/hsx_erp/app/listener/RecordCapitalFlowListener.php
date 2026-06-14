<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\ErpCapitalAccountService;

/**
 * 资金流水记录器（事件 RecordErpCapitalFlow 的应答方）
 *
 * 回收等插件在"确认打款"成功后，若操作员选择了出账户头，则发
 * event('RecordErpCapitalFlow', ['account_id'=>.., 'amount'=>.., 'direction'=>'out', ...])，
 * 本监听器调用资金账户服务记一笔流水并同步账户余额（出账即扣减）。
 *
 * 解耦：ERP 未安装则无人应答；任何异常都吞掉并返回 false，绝不影响调用方的打款主流程。
 *
 * Class RecordCapitalFlowListener
 * @package addon\hsx_erp\app\listener
 */
class RecordCapitalFlowListener
{
    public function handle(array $params): bool
    {
        try {
            $accountId = (int)($params['account_id'] ?? 0);
            $amount = round((float)($params['amount'] ?? 0), 2);
            $direction = (string)($params['direction'] ?? 'out');
            if ($accountId <= 0 || $amount <= 0) {
                return false;
            }
            if (!in_array($direction, ['in', 'out'], true)) {
                $direction = 'out';
            }

            (new ErpCapitalAccountService())->recordEntry([
                'account_id'        => $accountId,
                'direction'         => $direction,
                'amount'            => $amount,
                'biz_type'          => (string)($params['biz_type'] ?? 'recycle_payment'),
                'counterparty_id'   => (int)($params['counterparty_id'] ?? 0),
                'counterparty_name' => (string)($params['counterparty_name'] ?? ''),
                'source_type'       => (string)($params['source_type'] ?? ''),
                'source_no'         => (string)($params['source_no'] ?? ''),
                'source_id'         => (int)($params['source_id'] ?? 0),
                'remark'            => (string)($params['remark'] ?? ''),
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
