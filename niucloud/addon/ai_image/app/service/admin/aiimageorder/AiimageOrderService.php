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

namespace addon\ai_image\app\service\admin\aiimageorder;

use addon\ai_image\app\model\aiimageorder\AiimageOrder;
use app\model\member\Member;
use addon\ai_image\app\model\aiimagepackage\AiimagePackage;

use core\base\BaseAdminService;


/**
 * 订单列服务层
 * Class AiimageOrderService
 * @package addon\ai_image\app\service\admin\aiimageorder
 */
class AiimageOrderService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new AiimageOrder();
    }

    /**
     * 获取订单列列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,member_id,package_id,order_id,name,image,order_money,point,num,type,day,status,out_trade_no,pay_time,pid,create_time,close_time';
        $order = 'create_time desc';

        $search_model = $this->model->where([ [ 'site_id' ,"=", $this->site_id ] ])->withSearch(["member_id","package_id","order_id","name","image","status"], $where)->with(['member','aiimagePackage'])->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取订单列信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,member_id,package_id,order_id,name,image,order_money,point,num,type,day,status,out_trade_no,pay_time,pid,create_time,close_time';

        $info = $this->model->field($field)->where([['id', "=", $id]])->with(['member','aiimagePackage'])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 添加订单列
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $res = $this->model->create($data);
        return $res->id;

    }

    /**
     * 订单列编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {

        $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 删除订单列
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->find();
        $res = $model->delete();
        return $res;
    }
    
    public function getMemberAll(){
       $memberModel = new Member();
       return $memberModel->where([["site_id","=",$this->site_id]])->select()->toArray();
    }

    public function getAiimagePackageAll(){
       $aiimagePackageModel = new AiimagePackage();
       return $aiimagePackageModel->where([["site_id","=",$this->site_id]])->select()->toArray();
    }

}
