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
use addon\recycle\app\service\admin\quotation\QuotationConfigService;

/**
 * 报价单配置控制器
 * Class QuotationConfig
 * @package addon\recycle\app\adminapi\controller\quotation
 */
class QuotationConfig extends BaseAdminController
{
    /**
     * 获取报价单配置列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['quotation_id', ''],
            ['price_name', ''],
            ['is_enable', ''],
        ]);
        return success((new QuotationConfigService())->getPage($data));
    }

    /**
     * 报价单配置详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success((new QuotationConfigService())->getInfo($id));
    }

    /**
     * 添加报价单配置
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ['quotation_id', 0],
            ['price_name', ''],
            ['config_name', ''],
            ['default_price_value', 0],
            ['default_percentage_value', 0],
            ['price_adjustment_type', 0],
            ['price_adjustment_value', 0],
            ['quotation_background_color', ''],
            ['quotation_text_color', ''],
            ['authorization_token', ''],
            ['open_id', ''],
            ['is_enable', 1],
            ['auto_request', 1],
            ['request_time', '00:00'],
            ['remark', ''],
        ]);
        $this->validate($data, 'addon\recycle\app\validate\quotation\QuotationConfig.add');
        $id = (new QuotationConfigService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 报价单配置编辑
     * @param int $id
     * @return \think\Response
     */
    public function edit(int $id)
    {
        $data = $this->request->params([
            ['quotation_id', 0],
            ['price_name', ''],
            ['config_name', ''],
            ['default_price_value', 0],
            ['default_percentage_value', 0],
            ['price_adjustment_type', 0],
            ['price_adjustment_value', 0],
            ['quotation_background_color', ''],
            ['quotation_text_color', ''],
            ['authorization_token', ''],
            ['open_id', ''],
            ['is_enable', 1],
            ['auto_request', 1],
            ['request_time', '00:00'],
            ['remark', ''],
        ]);
        $this->validate($data, 'addon\recycle\app\validate\quotation\QuotationConfig.edit');
        (new QuotationConfigService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 报价单配置删除
     * @param int $id
     * @return \think\Response
     */
    public function del(int $id)
    {
        (new QuotationConfigService())->del($id);
        return success('DELETE_SUCCESS');
    }

    /**
     * 修改状态
     * @param int $id
     * @return \think\Response
     */
    public function modifyStatus(int $id)
    {
        $data = $this->request->params([
            ['is_enable', 1],
        ]);
        (new QuotationConfigService())->modifyStatus($id, $data['is_enable']);
        return success('EDIT_SUCCESS');
    }
}

