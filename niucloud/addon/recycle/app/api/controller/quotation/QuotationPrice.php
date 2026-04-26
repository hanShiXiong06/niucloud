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

namespace addon\recycle\app\api\controller\quotation;

use core\base\BaseApiController;
use addon\recycle\app\service\api\quotation\QuotationPriceService;

/**
 * 报价查询控制器（移动端）
 * Class QuotationPrice
 * @package addon\recycle\app\api\controller\quotation
 */
class QuotationPrice extends BaseApiController
{
    /**
     * 获取报价数据列表（移动端）
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['quotation_id', ''],
            ['price_name', ''],
            ['goods_name', ''],
            ['capacity', ''],
            ['price_date', ''],
            ['is_current', 1], // 默认查询当前报价
        ]);
        
        return success((new QuotationPriceService())->getList($data));
    }

    /**
     * 获取报价类型列表
     * @return \think\Response
     */
    public function getPriceTypes()
    {
        return success((new QuotationPriceService())->getPriceTypes());
    }
}

