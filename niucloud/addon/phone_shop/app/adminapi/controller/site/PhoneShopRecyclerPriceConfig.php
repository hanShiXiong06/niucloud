<?php
declare(strict_types=1);

namespace addon\phone_shop\app\adminapi\controller\site;

use addon\phone_shop\app\service\admin\site\PhoneShopRecyclePriceConfigService;
use core\base\BaseAdminController;

/**
 * 回收商价格配置控制器
 */
class PhoneShopRecyclerPriceConfig extends BaseAdminController
{
    
  

    /**
     * 添加价格配置
     */
    public function add()
    {
        $data = $this->request->post();
        $res = (new PhoneShopRecyclePriceConfigService())->add($data);
        return success($res);
    }

    /**
     * 获取价格配置信息
     */
    public function info($id)
    {
        $id = $this->request->param('id');
        
        $res = (new PhoneShopRecyclePriceConfigService())->getInfo((int)$id);
        return success($res);
    }

    /**
     * 根据site_id 获取回收商价格配置
     */
    public function getRecyclerPriceConfigBySiteId()
    {
        $site_id = $this->request->param('site_id');
        $res = (new PhoneShopRecyclePriceConfigService())->getRecyclerPriceConfigBySiteId((int)$site_id);
        return success($res);
    }
    /**
     * 更新价格配置
     */
    public function update($id)
    {
        $data = $this->request->put();
        
        $res = (new PhoneShopRecyclePriceConfigService())->update( $data);
        return success('更新成功');
    }

    /**
     * 删除价格配置
     */
    public function delete($id)
    {
        $res = (new PhoneShopRecyclePriceConfigService())->delete($id);
        return success($res);
    }
    /**
     * price_info
     * 获取价格配置的列表
     * */ 
    public function price_info()
    {
        $res = (new PhoneShopRecyclePriceConfigService())->price_info();
        return success($res);
    }
    // 更新价格配置的信息
    public function price_update()
    {
        $res = (new PhoneShopRecyclePriceConfigService())->price_update();
        return success($res);
    }
    // 删除价格配置的信息
    public function price_delete($id)
    {
        $res = (new PhoneShopRecyclePriceConfigService())->price_delete($id);
        return success($res);
    }
}

?>