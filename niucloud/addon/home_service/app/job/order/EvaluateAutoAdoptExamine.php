<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------
namespace addon\home_service\app\job\order;


use addon\home_service\app\dict\order\EvaluateDict;
use addon\home_service\app\model\order\Evaluate;
use addon\home_service\app\service\core\order\CoreGoodsEvaluateService;
use core\base\BaseJob;

/**
 * 队列异步调用评价自动通过审核
 */
class EvaluateAutoAdoptExamine extends BaseJob
{
    /**
     * 评价自动通过审核
     * @return true
     */
    public function doJob()
    {
        try {
            $list = (new Evaluate())->where([
                ['is_audit', '=', EvaluateDict::AUDIT],
                ['auto_adopt_time', '>', 0],
                ['auto_adopt_time', '<=', time()],
            ])->select();
            if (!$list->isEmpty()) {
                foreach ($list as $v) {
                    (new CoreGoodsEvaluateService())->autoAdoptExamine($v['evaluate_id']);
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}
