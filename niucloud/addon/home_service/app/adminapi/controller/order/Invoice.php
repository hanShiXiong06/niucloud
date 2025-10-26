<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\adminapi\controller\order;


use addon\home_service\app\service\admin\order\InvoiceService;
use core\base\BaseAdminController;


/**
 * 商品评价控制器
 * Class Invoice
 * @package addon\home_service\app\adminapi\controller\order
 */
class Invoice extends BaseAdminController
{
    /**
     * 获取发票列表
     * @description 查看商品评价-分页
     * @return \think\Response
     */
    public function page()
    {
        $data = $this->request->params([
            [ 'status', 'all' ],
            [ 'nickname', '' ],
            [ 'header_name', '' ],
            [ 'create_time', '' ],
        ]);
        return success(( new InvoiceService() )->getPage($data));
    }

    /**
     * 开具发票
     * @description 开具发票
     * @return \think\Response
     */
    public function issueInvoice($id)
    {
        $data = $this->request->params([
            [ 'email', '' ],
            [ 'invoice_number', '' ],
            [ 'invoice_voucher', '' ],
            [ 'money', '' ],
            [ 'remark', '' ],
        ]);
        ( new InvoiceService() )->issueInvoice($id,$data);
        return success();
    }


    /**
     * 订单发票详情
     * @description 订单发票详情
     * @return \think\Response
     */
    public function orderInvoiceInfo()
    {
        $data = $this->request->params([
            [ "order_id", 0 ],
        ]);
        return success(( new InvoiceService() )->orderInvoiceInfo($data));
    }
}
