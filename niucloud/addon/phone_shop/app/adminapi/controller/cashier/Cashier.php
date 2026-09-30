<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 代下单收银台控制器
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\adminapi\controller\cashier;

use core\base\BaseAdminController;
use addon\phone_shop\app\service\admin\cashier\CashierService;

/**
 * 代下单收银台(点菜式开单)
 */
class Cashier extends BaseAdminController
{
    /**
     * 收银台商品列表(富检索 + 会员价 + 质检详情)
     */
    public function goods()
    {
        $p = $this->request->params([
            [ 'keyword', '' ], [ 'imei', '' ], [ 'memory_group', [] ], [ 'condition_grade', [] ],
            [ 'category_id', 0 ], [ 'category_ids', [] ], [ 'member_id', 0 ], [ 'page', 1 ], [ 'limit', 18 ],
            [ 'sort_by', '' ], [ 'sort_direction', 'desc' ],
        ]);
        return success((new CashierService())->goods($p));
    }

    /**
     * 收银台分类树(三级,空分类不显示)
     */
    public function categoryTree()
    {
        return success((new CashierService())->categoryTree());
    }

    /**
     * 结算下单:直接生成 ERP 出货单
     */
    public function checkout()
    {
        $data = $this->request->params([
            [ 'member_id', 0 ],
            [ 'payment_mode', 'offline_cash' ],
            [ 'capital_account_id', 0 ],
            [ 'payments', [] ], // 多账户分笔现结 [{account_id, method, amount}]
            [ 'items', [] ],
        ]);
        return success((new CashierService())->checkout($data));
    }
}
