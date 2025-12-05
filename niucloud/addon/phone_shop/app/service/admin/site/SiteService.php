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

namespace addon\phone_shop\app\service\admin\site;

use addon\phone_shop\app\model\site\Site;

use core\base\BaseAdminService;


/**
 * 站点(二手)管理服务层
 * Class SiteService
 * @package addon\phone_shop\app\service\admin\site
 */
class SiteService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Site();
    }

    /**
     * 获取站点(二手)管理列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,site_name,client,category_status,brand_status,label_group_status,label_status,service_status,price_status';
        $order = '';

        $search_model = $this->model->where([ ])->withSearch(["id","site_name","client"], $where)->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取站点(二手)管理信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,site_name,client,category_status,brand_status,label_group_status,label_status,service_status,price_status';

        $info = $this->model->field($field)->where([['id', "=", $id]])->findOrEmpty()->toArray();
        $info['category_status'] = ($info['category_status']);
        $info['brand_status'] = ($info['brand_status']);
        $info['label_group_status'] =($info['label_group_status']);
        $info['label_status'] = ($info['label_status']);
        $info['service_status'] = ($info['service_status']);
        $info['site_id'] = ($info['site_id']);
        return $info;
    }

    /**
     * 添加站点(二手)管理
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        // 查询是否已经创建相关站点的配置 如果 是则提示 只能创建一次
        $result =  $this->model->where([['site_id', '=', $data['site_id']]])->find();
        if($result){
            throw new \Exception('该站点已创建相关配置，只能创建一次');
        }

        $res = $this->model->create($data);
        return $res->id;

    }

    /**
     * 站点(二手)管理编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
       
        $this->model->where([['id', '=', $id]])->update($data);
        return true;
    }

    /**
     * 删除站点(二手)管理
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['id', '=', $id]])->find();
        $res = $model->delete();
        return $res;
    }
    
}
