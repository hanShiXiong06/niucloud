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

namespace addon\tk_jhkd\app\adminapi\controller\shop_order;

use addon\tk_jhkd\app\service\admin\shop\OrderService;
use core\base\BaseAdminController;
use addon\tk_jhkd\app\service\admin\shop_order\ShopOrderService;
use core\exception\CommonException;


/**
 * 商城发单控制器
 * Class ShopOrder
 * @package addon\tk_jhkd\app\adminapi\controller\shop_order
 */
class ShopOrder extends BaseAdminController
{
   /**
    * 获取商城发单列表
    * @return \think\Response
    */
    public function lists(){
        if (!in_array('shop', (new OrderService())->checkShop())) {
            throw new CommonException('需要搭配niucloud-shop商城使用，暂无使用权限');
        }
        $data = $this->request->params([
             ["order_no",""],
             ["delivery_id",""],
             ["order_status_name",""],
             ["create_time",["",""]]
        ]);
        return success((new ShopOrderService())->getPage($data));
    }

    /**
     * 商城发单详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new ShopOrderService())->getInfo($id));
    }

    /**
     * 添加商城发单
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
             ["order_no",""],
             ["delivery_id",""],
             ["yida_order_no",""],
             ["order_status_name",""],
             ["is_pick",""],
             ["is_send",0],
             ["close_time",0],
             ["delete_time",0],

        ]);
        $this->validate($data, 'addon\tk_jhkd\app\validate\shop_order\ShopOrder.add');
        $id = (new ShopOrderService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 商城发单编辑
     * @param $id  商城发单id
     * @return \think\Response
     */
    public function edit(int $id){
        $data = $this->request->params([
             ["order_no",""],
             ["delivery_id",""],
             ["yida_order_no",""],
             ["order_status_name",""],
             ["is_pick",""],
             ["is_send",0],
             ["close_time",0],
             ["delete_time",0],

        ]);
        $this->validate($data, 'addon\tk_jhkd\app\validate\shop_order\ShopOrder.edit');
        (new ShopOrderService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 商城发单删除
     * @param $id  商城发单id
     * @return \think\Response
     */
    public function del(int $id){
        (new ShopOrderService())->del($id);
        return success('DELETE_SUCCESS');
    }

    
}
