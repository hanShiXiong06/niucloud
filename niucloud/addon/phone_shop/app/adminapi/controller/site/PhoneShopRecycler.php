<?php
/**
 * 回收商配置
 * */ 
namespace addon\phone_shop\app\adminapi\controller\site;

use addon\phone_shop\app\service\admin\site\PhoneShopRecyclerService;
use core\base\BaseAdminController;

class PhoneShopRecycler extends BaseAdminController
{
  

    // 添加回收商
    public function add()
    {
        $data = $this->request->param();
        (new PhoneShopRecyclerService())->addRecycler($data);
        return success('添加成功');
    }
    // 查询回收商信息
    public function info()
    {
        
        $info = (new PhoneShopRecyclerService())->getRecyclerInfo();
        return success($info);
    }
    // 更新回收商信息
    public function update()
    {
        $id = $this->request->param('id');
        $data = $this->request->param();
        (new PhoneShopRecyclerService())->updateRecycler($id, $data);
        return success('更新成功');
    }
    // 删除回收商
    public function delete()
    {
        $id = $this->request->param('id');
        (new PhoneShopRecyclerService())->deleteRecycler($id);
        return success('删除成功');
    }
    // 获取role身份
    public function role(){
        
        return success((new PhoneShopRecyclerService())->role());
    }

}
