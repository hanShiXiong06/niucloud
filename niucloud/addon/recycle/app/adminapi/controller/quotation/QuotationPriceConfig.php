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
use addon\recycle\app\service\admin\quotation\QuotationPriceConfigService;

/**
 * 价格配置控制器
 * Class QuotationPriceConfig
 * @package addon\recycle\app\adminapi\controller\quotation
 */
class QuotationPriceConfig extends BaseAdminController
{
    /**
     * 获取价格配置列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['config_type', ''],
            ['goods_id', ''],
            ['is_enable', ''],
        ]);
        return success((new QuotationPriceConfigService())->getPage($data));
    }

    /**
     * 价格配置详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success((new QuotationPriceConfigService())->getInfo($id));
    }

    /**
     * 添加价格配置
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ['config_type', 1],
            ['goods_id', 0],
            ['capacity', ''],
            ['capacity_answer_id', 0],
            ['config_item_name', ''],
            ['group_key', 0],
            ['adjustment_type', 1],
            ['adjustment_value', 0],
            ['is_enable', 1],
            ['title', ''],
        ]);
        $this->validate($data, 'addon\recycle\app\validate\quotation\QuotationPriceConfig.add');
        $id = (new QuotationPriceConfigService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 价格配置编辑
     * @param int $id
     * @return \think\Response
     */
    public function edit(int $id)
    {
        $data = $this->request->params([
            ['config_type', 1],
            ['goods_id', 0],
            ['capacity', ''],
            ['capacity_answer_id', 0],
            ['config_item_name', ''],
            ['group_key', 0],
            ['adjustment_type', 1],
            ['adjustment_value', 0],
            ['is_enable', 1],
            ['title', ''],
            ['sku_list', []],
        ]);
        $this->validate($data, 'addon\recycle\app\validate\quotation\QuotationPriceConfig.edit');
        (new QuotationPriceConfigService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 价格配置删除
     * @param int $id
     * @return \think\Response
     */
    public function del(int $id)
    {
        (new QuotationPriceConfigService())->del($id);
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
        (new QuotationPriceConfigService())->modifyStatus($id, $data['is_enable']);
        return success('EDIT_SUCCESS');
    }

    /**
     * 批量删除价格配置
     * @return \think\Response
     */
    public function batchDel()
    {
        $data = $this->request->params([
            ['ids', []],
        ]);
        if (empty($data['ids']) || !is_array($data['ids'])) {
            return fail('请选择要删除的配置');
        }
        (new QuotationPriceConfigService())->batchDel($data['ids']);
        return success('DELETE_SUCCESS');
    }

    /**
     * 批量添加SKU配置（智能合并）
     * @return \think\Response
     */
    public function batchAddSku()
    {
        $data = $this->request->params([
            ['skus', []],
            ['adjustment_type', 1],
            ['adjustment_value', 0],
            ['is_enable', 1],
        ]);
        
        if (empty($data['skus']) || !is_array($data['skus'])) {
            return fail('SKU列表不能为空');
        }
        
        $result = (new QuotationPriceConfigService())->batchAddSkuConfig(
            $data['skus'],
            intval($data['adjustment_type']),
            floatval($data['adjustment_value']),
            intval($data['is_enable'])
        );
        
        return success('ADD_SUCCESS', $result);
    }

    /**
     * 清空所有价格配置
     * @return \think\Response
     */
    public function clearAll()
    {
        (new QuotationPriceConfigService())->clearAll();
        return success('DELETE_SUCCESS');
    }

   
}
