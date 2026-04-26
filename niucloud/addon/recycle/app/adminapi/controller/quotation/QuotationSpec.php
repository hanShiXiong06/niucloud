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
use addon\recycle\app\service\admin\quotation\QuotationSpecService;

/**
 * 报价规格管理控制器
 * Class QuotationSpec
 * @package addon\recycle\app\adminapi\controller\quotation
 */
class QuotationSpec extends BaseAdminController
{
    /**
     * 获取型号列表
     * @return \think\Response
     */
    public function modelLists()
    {
        $data = $this->request->params([
            ['goods_name', ''],
            ['sync_enable', ''],
        ]);
        return success((new QuotationSpecService())->getModelList($data));
    }

    /**
     * 设置型号同步状态
     * @param int $id
     * @return \think\Response
     */
    public function setModelSyncStatus(int $id)
    {
        $data = $this->request->params([
            ['sync_enable', 1],
        ]);
        (new QuotationSpecService())->setModelSyncStatus($id, $data['sync_enable']);
        return success('操作成功');
    }

    /**
     * 批量设置型号同步状态
     * @return \think\Response
     */
    public function batchSetModelSyncStatus()
    {
        $data = $this->request->params([
            ['ids', []],
            ['sync_enable', 1],
        ]);
        (new QuotationSpecService())->batchSetModelSyncStatus($data['ids'], $data['sync_enable']);
        return success('操作成功');
    }

    /**
     * 获取内存列表
     * @return \think\Response
     */
    public function capacityLists()
    {
        $data = $this->request->params([
            ['model_id', ''],
            ['goods_id', ''],
            ['capacity', ''],
            ['sync_enable', ''],
        ]);
        return success((new QuotationSpecService())->getCapacityList($data));
    }

    /**
     * 设置内存同步状态
     * @param int $id
     * @return \think\Response
     */
    public function setCapacitySyncStatus(int $id)
    {
        $data = $this->request->params([
            ['sync_enable', 1],
        ]);
        (new QuotationSpecService())->setCapacitySyncStatus($id, $data['sync_enable']);
        return success('操作成功');
    }

    /**
     * 批量设置内存同步状态
     * @return \think\Response
     */
    public function batchSetCapacitySyncStatus()
    {
        $data = $this->request->params([
            ['ids', []],
            ['sync_enable', 1],
        ]);
        (new QuotationSpecService())->batchSetCapacitySyncStatus($data['ids'], $data['sync_enable']);
        return success('操作成功');
    }

    /**
     * 获取等级规格列表
     * @return \think\Response
     */
    public function gradeSpecLists()
    {
        $data = $this->request->params([
            ['spec_name', ''],
            ['sync_enable', ''],
        ]);
        return success((new QuotationSpecService())->getGradeSpecList($data));
    }

    /**
     * 设置等级规格同步状态
     * @param int $id
     * @return \think\Response
     */
    public function setGradeSpecSyncStatus(int $id)
    {
        $data = $this->request->params([
            ['sync_enable', 1],
        ]);
        (new QuotationSpecService())->setGradeSpecSyncStatus($id, $data['sync_enable']);
        return success('操作成功');
    }

    /**
     * 批量设置等级规格同步状态
     * @return \think\Response
     */
    public function batchSetGradeSpecSyncStatus()
    {
        $data = $this->request->params([
            ['ids', []],
            ['sync_enable', 1],
        ]);
        (new QuotationSpecService())->batchSetGradeSpecSyncStatus($data['ids'], $data['sync_enable']);
        return success('操作成功');
    }

    /**
     * 获取同步规格统计
     * @return \think\Response
     */
    public function getSyncStats()
    {
        return success((new QuotationSpecService())->getSyncStats());
    }
}

