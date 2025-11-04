<?php

namespace addon\recycle\app\adminapi\controller\quotation;

use addon\recycle\app\service\admin\quotation\DeductionConfigService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 扣费配置控制器
 */
class DeductionConfig extends BaseAdminController
{
    /**
     * 获取扣费配置分页列表
     */
    public function pages()
    {
        $data = $this->request->params([
            ['config_name', ''],
            ['goods_series', ''],
            ['price_type', ''],
            ['is_enable', '']
        ]);

        return success((new DeductionConfigService())->getPage($data));
    }

    /**
     * 获取扣费配置列表
     */
    public function lists()
    {
        $data = $this->request->params([
            ['goods_series', ''],
            ['price_type', '']
        ]);

        return success((new DeductionConfigService())->getList($data));
    }

    /**
     * 获取扣费配置详情
     */
    public function info(int $id)
    {
        return success((new DeductionConfigService())->getInfo($id));
    }

    /**
     * 添加扣费配置
     */
    public function add()
    {
        $data = $this->request->params([
            ['config_name', ''],
            ['remark_text', ''],
            ['sort', 0],
            ['is_enable', 1]
        ]);

        $this->validate($data, [
            'config_name' => 'require',
        ]);

        $id = (new DeductionConfigService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 编辑扣费配置
     */
    public function edit(int $id)
    {
        $data = $this->request->params([
            ['config_name', ''],
            ['remark_text', ''],
            ['sort', 0],
            ['is_enable', 1]
        ]);

        $this->validate($data, [
            'config_name' => 'require',

        ]);

        (new DeductionConfigService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 删除扣费配置
     */
    public function del(int $id)
    {
        (new DeductionConfigService())->del($id);
        return success('DELETE_SUCCESS');
    }

    /**
     * 修改状态
     */
    public function modifyStatus()
    {
        $data = $this->request->params([
            ['id', 0],
            ['is_enable', 1]
        ]);

        (new DeductionConfigService())->modifyStatus($data['id'], $data['is_enable']);
        return success('MODIFY_SUCCESS');
    }
}

