<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpOutboundService;
use core\base\BaseAdminController;
use think\App;

/**
 * ERP 出库 / 调拨(同行出货)
 */
class Outbound extends BaseAdminController
{
    protected ErpOutboundService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpOutboundService();
    }

    public function lists()
    {
        $where = $this->request->params([
            ['outbound_type', ''], ['price_status', ''], ['keyword', ''],
            ['start_time', 0], ['end_time', 0], ['amount_min', ''], ['amount_max', ''],
            ['sort_field', ''], ['sort_order', ''], ['page', 1], ['limit', 15],
        ]);
        return success($this->service->getPage($where));
    }

    public function info(int $id)
    {
        return success($this->service->getInfo($id));
    }

    /** 创建出库单(同行销售/报废) */
    public function create()
    {
        $p = $this->request->params([
            ['outbound_type', 'peer_sale'], ['counterparty_id', 0], ['counterparty_name', ''],
            ['counterparty_enterprise_id', 0], ['settle_mode', 'now'], ['capital_account_id', 0],
            ['remark', ''], ['express_no', ''], ['items', []],
        ]);
        return success($this->service->createOutbound($p));
    }

    /** 退回/取消出库(仅挂单/未定价、未收款) */
    public function cancel(int $id)
    {
        return success($this->service->cancelOutbound($id, (string)$this->request->param('reason', '')));
    }

    /** 回填价格(价格未来回填的出库单), 可选立即收款入账 */
    public function fillPrice(int $id)
    {
        $p = $this->request->params([
            ['items', []], ['collect_now', 0], ['capital_account_id', 0],
        ]);
        return success($this->service->fillPrice($id, (array)$p['items'], [
            'collect_now'        => (int)$p['collect_now'] === 1,
            'capital_account_id' => (int)$p['capital_account_id'],
        ]));
    }

    /** 卖同行待办: 待回填价 / 待收款 的同行挂单(设备中心专门处理页用) */
    public function peerSaleTodo()
    {
        $where = $this->request->params([
            ['keyword', ''], ['imei', ''], ['express_no', ''], ['operator', ''], ['state', ''],
            ['start_time', 0], ['end_time', 0], ['qty_min', ''], ['qty_max', ''],
            ['page', 1], ['limit', 15],
        ]);
        return success($this->service->peerSaleTodo($where));
    }

    /** 调拨(移仓/移库位, 如划拨到同行仓)。consign_action: list 上架代卖(默认) / buyout 我方买断 */
    public function transfer()
    {
        $p = $this->request->params([
            ['asset_ids', []], ['to_warehouse_id', 0], ['to_location_id', 0], ['remark', ''],
            ['consign_action', 'list'], ['buyout_prices', []],
        ]);
        return success($this->service->transfer(
            (array)$p['asset_ids'],
            (int)$p['to_warehouse_id'],
            (int)$p['to_location_id'],
            (string)$p['remark'],
            [
                'consign_action' => (string)$p['consign_action'],
                'buyout_prices'  => (array)$p['buyout_prices'],
            ]
        ));
    }
}
