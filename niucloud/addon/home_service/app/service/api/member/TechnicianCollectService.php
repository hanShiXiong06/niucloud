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

namespace addon\home_service\app\service\api\member;

use addon\home_service\app\model\goods\Goods;
use addon\home_service\app\model\technician\Technician;
use addon\home_service\app\model\technician\TechnicianCollect;
use addon\home_service\app\service\core\strategy\CoreCityStrategyService;
use app\model\member\Member;
use core\base\BaseApiService;
use core\exception\ApiException;


/**
 * 师傅收藏收藏层
 * Class TechnicianCollectService
 * @package addon\home_service\app\service\api\goods
 */
class TechnicianCollectService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new TechnicianCollect();
    }


    /**
     * 商品收藏列表
     */
    public function getMemberTechnicianCollectList()
    {
        $search_model = $this->model->where([['member_id', '=', $this->member_id], ['site_id', '=', $this->site_id]])
            ->with(['technician' =>function($query){
                $query->field('site_id,id,real_name,order_num,service_time,headimg,full_address,evaluate_avg_scores,category_id,status')->append(['category_name','status_name']);
            }])
            ->order('create_time desc');
        $list = $this->pageQuery($search_model);

        foreach ($list['data'] as &$value){
            if (!empty($value['technician'])){
                $value['technician']['evaluate_avg_scores'] = number_format($value['technician']['evaluate_avg_scores'], 1, '.', '');
            }
        }
        return $list;
    }
    /**
     * 商品添加收藏
     */
    public function addTechnicianCollect($data)
    {
        $data['member_id'] = $this->member_id;
        $data['site_id'] = $this->site_id;
        $info = $this->model->where([
            ['member_id', '=', $data['member_id']],
            ['technician_id', '=', $data['technician_id']],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty()->toArray();
        $technician_info = (new Technician())->where([
            ['id', '=', $data['technician_id']],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty()->toArray();
        if (empty($technician_info)) throw new ApiException("HOME_SERVICE_TECHNICIAN_NOT_EXIST");
        if (!empty($info)) {
            throw new ApiException('HOME_SERVICE_TECHNICIAN_ALREADY_COLLECT');//已收藏
        } else {
            // 添加
            $data['create_time'] = time();
            $res = $this->model->create($data);
            return $res->id;
        }
    }


    /**
     * 商品取消收藏
     */
    public function cancelTechnicianCollect($data)
    {
        $res = $this->model->where([['technician_id', '=', $data['technician_id']], ['member_id', '=', $this->member_id], ['site_id', '=', $this->site_id]])->delete();
        return $res;
    }


    /**
     * 师傅是否收藏
     */
    public function getTechnicianIsCollect($technician_id): int
    {
        $collect_info = $this->model
            ->where([['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id], ['technician_id', '=', $technician_id]])
            ->findOrEmpty()
            ->toArray();
        if (!empty($collect_info)) {
            return 1;
        } else {
            return 0;
        }
    }


}
