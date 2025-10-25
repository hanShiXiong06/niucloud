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

namespace addon\recycle\app\service\api\recycle_order;

use addon\recycle\app\model\recycle_order\PhoneShopRecycleOrder;
use app\model\member\Member;
use addon\recycle\app\dict\RecycleOrderDict;

use core\base\BaseApiService;


/**
 * 二手机回收报价订单服务层
 * Class PhoneShopRecycleOrderService
 * @package addon\recycle\app\service\admin\recycle_order
 */
class PhoneShopRecycleOrderService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new PhoneShopRecycleOrder();
    }

    /**
     * 获取二手机回收报价订单列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,member_id,site_id,count,express_id,send_username,telphone,pay_type,account,status,create_at,over_at,comment,close_express_id';
        $order = 'create_at asc';


        $search_model = $this->model->where([ [ 'site_id' ,"=", $this->site_id   ],['member_id','=', $this->member_id] ])->withSearch(["id","count","express_id","send_username","telphone","pay_type","account","status","create_at","over_at","comment","close_express_id"], $where)->with(['member'])->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        
        return $list;
    }

    /**
     * 获取二手机回收报价订单信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,member_id,site_id,count,express_id,send_username,telphone,pay_type,account,status,create_at,over_at,comment,close_express_id';

        $info = $this->model->field($field)->where([['id', "=", $id]])->with(['member'])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 添加二手机回收报价订单
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['member_id'] = $this->member_id;
        $data['create_at'] = time();

        $res = $this->model->create($data);
        return $res->id;

    }

    /**
     * 二手机回收报价订单编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        $data['update_at'] = time();
        $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 删除二手机回收报价订单
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
    //    $memberModel = new Member();
       return [] ;//  $memberModel->where([["site_id","=",$this->site_id]])->select()->toArray();
    }

    
    // getStatus
    /**
     * 充值订单状态
     * @return array|array[]|string
     */
    public function getStatus()
    {
        return RecycleOrderDict::getStatus();
    }
}

