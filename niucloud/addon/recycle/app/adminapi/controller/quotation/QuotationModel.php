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

use addon\recycle\app\service\admin\quotation\QuotationModelService;
use core\base\BaseAdminController;

/**
 * 报价型号控制器
 * Class QuotationModel
 * @package addon\recycle\app\adminapi\controller\quotation
 */
class QuotationModel extends BaseAdminController
{
    /**
     * 获取型号列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['goods_id', ''],
            ['goods_name', ''],
            ['status', ''],
            ['create_at', ['', '']],
            ['update_at', ['', '']]
        ]);
        return success((new QuotationModelService())->getList($data));
    }
}