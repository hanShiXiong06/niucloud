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

use addon\home_service\app\dict\order\EvaluateDict;
use addon\home_service\app\service\admin\order\EvaluateService;
use core\base\BaseAdminController;


/**
 * 商品评价控制器
 * Class Evaluate
 * @package addon\home_service\app\adminapi\controller\order
 */
class Evaluate extends BaseAdminController
{
    /**
     * 获取商品评价列表
     * @description 查看商品评价-分页
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['is_audit', ''],
            ['order_no', ''],
            ['member_search', ''],
            ['join_create_time', []],
            ['technician_name', ''],
            ['store_name', ''],
            ['technician_id', ''],
            ['store_id', ''],
        ]);
        return success((new EvaluateService())->getPage($data));
    }


    public function detail(int $order_id)
    {
        $res = (new EvaluateService())->getDetail($order_id);
        return success($res);
    }

    /**
     * 商品评价删除
     * @description 删除商品评价
     * @param $id  商品评价id
     * @return \think\Response
     */
    public function del(int $id)
    {
        (new EvaluateService())->del($id);
        return success('DELETE_SUCCESS');
    }

    /**
     * 审核通过
     * @description 审核-通过评价
     * @param $id
     * @return \think\Response
     */
    public function adopt($id)
    {
        (new EvaluateService())->auditAdopt($id);

        return success('SUCCESS');
    }

    /**
     * 审核拒绝
     * @description 审核-拒绝通过评价
     * @param $id
     * @return \think\Response
     */
    public function refuse($id)
    {
        (new EvaluateService())->auditRefuse($id);

        return success('SUCCESS');
    }


    /**
     * 获取审核状态
     * @description 审核状态
     * @return \think\Response
     */
    public function status()
    {
        return success(EvaluateDict::getStatus());
    }


    public function taskStatus()
    {
        $data = $this->request->params([
            ['technician_id', ''],
            ['store_id', ''],
        ]);
        return success((new EvaluateService())->getTaskStatus($data));
    }


    /**
     * 批量通过
     * @description 批量通过
     */
    public function batchAdopt()
    {
        $data = $this->request->params([
            ['evaluate_ids', []],
        ]);
        (new EvaluateService())->batchAdopt($data['evaluate_ids']);
        return success('SUCCESS');
    }

    /**
     * 批量拒绝
     * @description 批量拒绝
     */
    public function batchRefuse()
    {
        $data = $this->request->params([
            ['evaluate_ids', []],
        ]);
        (new EvaluateService())->batchRefuse($data['evaluate_ids']);
        return success('SUCCESS');
    }


    /**
     * 批量删除
     * @description 批量删除
     */
    public function batchDel()
    {
        $data = $this->request->params([
            ['evaluate_ids', []],
        ]);
        (new EvaluateService())->batchDel($data['evaluate_ids']);
        return success('SUCCESS');
    }
}
