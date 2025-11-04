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

namespace addon\recycle\app\adminapi\controller\quotation;

use core\base\BaseAdminController;
use addon\recycle\app\service\admin\quotation\QuotationDataService;

/**
 * 报价数据控制器
 * Class QuotationData
 * @package addon\recycle\app\adminapi\controller\quotation
 */
class QuotationData extends BaseAdminController
{
    /**
     * 获取报价数据列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['quotation_id', ''],
            ['price_name', ''],
            ['goods_id', ''],
            ['goods_name', ''],
            ['capacity', ''],
            ['price_date', ''],
            ['is_current', ''],
        ]);
        return success((new QuotationDataService())->getPage($data));
    }

    /**
     * 获取所有报价数据（不分页，用于表格展示）
     * @return \think\Response
     */
    public function getAll()
    {
        $data = $this->request->params([
            ['quotation_id', ''],
            ['price_name', ''],
            ['goods_id', ''],
            ['goods_name', ''],
            ['capacity', ''],
            ['price_date', ''],
            ['is_current', ''],
        ]);
        return success((new QuotationDataService())->getAll($data));
    }

    /**
     * 报价数据详情
     * @return \think\Response
     */
    public function info()
    {
        $id = (int)$this->request->param('id', 0);
        return success((new QuotationDataService())->getInfo($id));
    }

    /**
     * 获取级联选项（型号、内存、配置项）
     * @return \think\Response
     */
    public function getCascadeOptions()
    {
        $quotationId = $this->request->param('quotation_id', '');
        $priceName = $this->request->param('price_name', '');
        
        $quotationId = $quotationId !== '' && $quotationId !== null ? (int)$quotationId : null;
        $priceName = $priceName !== '' && $priceName !== null ? $priceName : null;
        
        return success((new QuotationDataService())->getCascadeOptions($quotationId, $priceName));
    }

    /**
     * 批量修改报价数据（调价）
     * @return \think\Response
     */
    public function batchUpdatePrice()
    {
        $data = $this->request->params([
            ['items', []],
        ]);
        return success('批量修改成功', (new QuotationDataService())->batchUpdatePrice($data['items']));
    }
}
