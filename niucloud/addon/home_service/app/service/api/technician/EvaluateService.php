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

namespace addon\home_service\app\service\api\technician;

use addon\home_service\app\dict\order\EvaluateDict;
use addon\home_service\app\model\order\Evaluate;
use addon\home_service\app\service\api\technician\TechnicianTrait;
use addon\home_service\app\service\core\order\CoreGoodsEvaluateService;
use addon\home_service\app\service\core\order\CoreOrderConfigService;
use addon\home_service\app\service\core\order\SubStatusTrait;
use app\model\member\Member;
use core\exception\ApiException;
use core\exception\CommonException;
use core\base\BaseApiService;
use think\facade\Db;


/**
 * 商品评价服务层
 * Class EvaluateService
 * @package addon\home_service\app\service\admin\order
 */
class EvaluateService extends BaseApiService
{
    use TechnicianTrait;

    public function __construct()
    {
        parent::__construct();
        $this->model = new Evaluate();
        $this->checkTechnician();
    }

    /**
     * 获取商品评价列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'evaluate_id,site_id,order_id,goods_id,member_id,member_name,member_head,content,images,is_anonymous,scores,is_audit,explain_first,create_time,update_time';
        $order = 'create_time ' . $where['sort'];
        if (!empty($where['order'])){
            $order = $where['order'] .' '. $where['sort'];
        }
        $search_model = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['technician_id', '=', $this->technician_id],
            ['is_audit', 'in', [EvaluateDict::AUDIT_NO, EvaluateDict::AUDIT_ADOPT]],
        ])->field($field)->with([
            'order'=>function($query){
                $query->field('order_id,order_name')->with(['itemImage']);
            },
        ])->order($order)->append(['image_mid','member_name']);
        $list = $this->pageQuery($search_model);
        return $list;
    }

}
