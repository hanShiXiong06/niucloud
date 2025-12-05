<?php
/**
 * 回收商配置
 * */ 
namespace addon\phone_shop\app\service\admin\site;

use addon\phone_shop\app\model\site\PhoneShopRecycler;
use addon\phone_shop\app\model\site\Site;
use core\base\BaseAdminService;

class PhoneShopRecyclerService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new PhoneShopRecycler();
    }

    // 添加回收商
    public function addRecycler($data)
    {
        // 需要判断当前的站点是否具备回收商的资格
        $site = Site::where('site_id', $this->site_id)->find();
        // if ($site['client'] != 1) {
        //     throw new \Exception('当前站点不具备回收商的资格,请联系管理员');
        // }
        // 添加回收商 create 
        $data['site_id']= $this->site_id;
        $this->model->create($data);
    }
    // 查询回收商信息 
    public function getRecyclerInfo()
    {
      // 查询回收商信息 返回 site_id 对应的 信息
      $info =  $this->model->field("*")->where([['site_id', "=", $this->site_id]])->findOrEmpty()->toArray();
      return $info;
  
    }
    // 更新回收商信息
    public function updateRecycler($id, $data)
    {
        return $this->model->where('id', $id)->update($data);
    }
    
    // 删除回收商
    public function deleteRecycler($id)
    {
        return $this->model->where('id', $id)->delete();
    }
    // role
    public function role(){
        // 
    
     $field = 'client';
     $info = (new Site())->field($field)->where([['site_id', "=", $this->site_id]])->findOrEmpty()->toArray();
     return $info;
 
    } 

}
