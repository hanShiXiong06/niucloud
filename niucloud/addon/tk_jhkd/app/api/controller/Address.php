<?php
namespace addon\tk_jhkd\app\api\controller;
use core\base\BaseController;
use addon\tk_jhkd\app\service\api\AddressService;
use think\Response;
class Address extends BaseController
{
    public function fanyiAdress()
    {
        $data = $this->request->params([
            ["address",""],
        ]);
        return success((new AddressService())->fanyiAddress($data['address']));
    }
    /**
     *地址详细信息/适配框架地址库
     */
    public function getInfo($id){
        return success('获取成功',(new AddressService())->getMemberAddressInfo($id));
    }

    /**
     * 获取聚合快递通知列表列表
     * @return \think\Response
     */
    public function lists(){
        $data = $this->request->params([]);
        return success((new AddressService())->getPage($data));
    }

    /**
     * 聚合快递通知列表详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new AddressService())->getInfo($id));
    }

    /**
     * 添加聚合快递通知列表
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
            ["name",""],
            ["mobile",""],
            ["city_id",""],
            ["latitude",""],
            ["longitude",""],
            ["address",""],
            ["full_address",""]
        ]);
        $this->validate($data, 'addon\tk_jhkd\app\validate\TkjhkdAddress.add');
        $id = (new AddressService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 聚合快递通知列表编辑
     * @param int $id  聚合快递通知列表id
     * @return \think\Response
     */
    public function edit($id){
        $data = $this->request->params([
            ["id",""],
            ["name",""],
            ["mobile",""],
            ["city_id",""],
            ["latitude",""],
            ["longitude",""],
            ["address",""],
            ["full_address",""]
        ]);
        (new AddressService())->edit($id, $data);
        return success('EDIT_SUCCESS',$data);
    }

    /**
     * 聚合快递通知列表删除
     * @param int $id  聚合快递通知列表id
     * @return \think\Response
     */
    public function del(int $id){
        (new AddressService())->del($id);
        return success('DELETE_SUCCESS');
    }


}