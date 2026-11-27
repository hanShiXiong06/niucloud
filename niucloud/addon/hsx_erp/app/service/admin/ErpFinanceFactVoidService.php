<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpPayable;
use addon\hsx_erp\app\model\ErpReceivable;
use core\exception\CommonException;

/** 仅允许来源插件作废自己创建且尚未发生结算的财务事实。 */
class ErpFinanceFactVoidService extends ErpExternalContractService
{
    public const EVENT_NAME = 'ErpFinanceFactVoidRequested';
    public const CONTRACT_NAME = 'erp.finance.fact_void_requested.v1';
    public const CONTRACT_VERSION = 1;

    public function consume(array $event): array
    {
        $payload = $this->normalizePayload($event);
        return $this->consumeOnce($payload, self::EVENT_NAME, function (array $request): array {
            return $this->voidFact($request);
        });
    }

    protected function normalizePayload(array $event): array
    {
        $targetType = trim((string)($event['target_type'] ?? ''));
        if (!in_array($targetType, ['receivable', 'payable'], true)) {
            throw new CommonException('财务事实作废目标必须是receivable或payable');
        }
        $targetId = max(0, (int)($event['target_id'] ?? 0));
        $reason = mb_substr(trim((string)($event['reason'] ?? $event['remark'] ?? '')), 0, 255);
        if ($targetId <= 0) throw new CommonException('财务事实作废请求缺少target_id');
        if ($reason === '') throw new CommonException('作废财务事实必须填写原因');

        return array_merge(
            $this->normalizeEnvelope($event, self::CONTRACT_NAME, self::CONTRACT_VERSION),
            [
                'event_name' => self::CONTRACT_NAME,
                'event_version' => self::CONTRACT_VERSION,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'reason' => $reason,
            ]
        );
    }

    protected function voidFact(array $payload): array
    {
        $targetType = (string)$payload['target_type'];
        $model = $targetType === 'receivable' ? ErpReceivable::class : ErpPayable::class;
        $numberField = $targetType === 'receivable' ? 'receivable_no' : 'payable_no';
        $row = $model::where([
            ['site_id', '=', (int)$payload['site_id']],
            ['id', '=', (int)$payload['target_id']],
        ])->lock(true)->findOrEmpty();
        if ($row->isEmpty()) throw new CommonException('应收应付目标不存在或不属于当前站点');
        if ((string)$row->origin_plugin !== (string)$payload['source_plugin']) {
            throw new CommonException('外部插件只能作废由自身创建的财务事实');
        }
        if ((string)$row->status !== ErpDict::STATUS_PENDING || abs((float)$row->settled_amount) > 0.0001) {
            throw new CommonException('已经部分或全部结算的财务事实不能直接作废');
        }
        $category = (new ErpConfigService())->findFinanceCategory((string)$row->category_key);
        if ((int)($category['affects_asset_cost'] ?? 0) === 1 || ((int)$row->asset_id > 0 && (string)$row->category_key === 'refurbish_cost')) {
            throw new CommonException('该财务事实影响设备成本，必须通过对应业务冲正流程处理');
        }

        $now = time();
        $oldRemark = trim((string)$row->remark);
        $voidRemark = '已由' . (string)$payload['source_plugin'] . '作废：' . (string)$payload['reason'];
        $row->save([
            'status' => ErpDict::STATUS_VOID,
            'remark' => mb_substr($oldRemark === '' ? $voidRemark : $oldRemark . '；' . $voidRemark, 0, 255),
            'update_at' => $now,
        ]);
        $targetNo = (string)($row->{$numberField} ?? '');
        (new ErpLedgerService())->account([
            'biz_type' => 'finance_fact_void',
            'direction' => 'decrease',
            'amount' => round((float)$row->amount, 2),
            'balance_after' => 0,
            'party_id' => (int)$row->party_id,
            'party_name' => (string)$row->party_name,
            'asset_id' => (int)$row->asset_id,
            'source_type' => $targetType,
            'source_id' => (int)$row->id,
            'source_no' => $targetNo,
            'occurred_at' => (int)$payload['occurred_at'],
            'remark' => mb_substr('作废' . ($targetType === 'receivable' ? '应收' : '应付') . '事实：' . (string)$payload['reason'], 0, 255),
        ]);

        return [
            'target_type' => $targetType,
            'target_id' => (int)$row->id,
            'target_no' => $targetNo,
            'target_status' => ErpDict::STATUS_VOID,
            'voided_amount' => number_format((float)$row->amount, 2, '.', ''),
            'voided_at' => $now,
        ];
    }
}
