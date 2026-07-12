<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpPurchaseReturnService;
use core\base\BaseAdminController;
use think\App;

class ErpPurchaseReturn extends BaseAdminController
{
    protected ErpPurchaseReturnService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpPurchaseReturnService();
    }

    public function lists()
    {
        $params = $this->request->params([
            ['keyword', ''],
            ['status', ''],
            ['party_id', 0],
            ['start_at', 0],
            ['end_at', 0],
            ['page', 1],
            ['limit', 15],
        ]);
        return success($this->service->lists($params));
    }

    public function info(int $id)
    {
        return success($this->service->info($id));
    }

    public function create()
    {
        $params = $this->request->params([
            ['purchase_order_id', 0],
            ['refund_mode', 'receivable'],
            ['capital_account_id', 0],
            ['voucher_urls', ''],
            ['remark', ''],
            ['items', []],
            ['request_id', ''],
        ]);
        $ids = (int)$params['purchase_order_id'] > 0
            ? [$this->service->create($params)]
            : $this->service->createBatch($params);
        $id = (int)($ids[0] ?? 0);
        $result = $this->service->info($id);
        $returnNos = [];
        foreach ($ids as $returnId) {
            $returnInfo = $this->service->info((int)$returnId);
            $returnNos[] = (string)($returnInfo['return_no'] ?? '');
        }
        return success([
            'id' => $id,
            'ids' => $ids,
            'return_nos' => array_values(array_filter($returnNos)),
            'return_no' => (string)($result['return_no'] ?? ''),
            'refund_mode' => (string)($result['refund_mode'] ?? 'none'),
            'refund_receivable' => $result['refund_receivable'] ?? null,
        ]);
    }

    public function confirm(int $id)
    {
        $params = $this->request->params([
            ['remark', ''],
        ]);
        return success($this->service->confirm($id, $params));
    }

    public function cancel(int $id)
    {
        $params = $this->request->params([
            ['remark', ''],
        ]);
        return success($this->service->cancel($id, (string)$params['remark']));
    }
}
