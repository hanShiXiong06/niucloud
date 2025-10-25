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

namespace addon\recycle\app\service\api\recycle_category;

use addon\recycle\app\model\recycle_category\RecycleBanner;
use core\base\BaseApiService;

/**
 * Banner前端服务层
 * Class RecycleBannerService
 * @package addon\recycle\app\service\api\recycle_category
 */
class RecycleBannerService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleBanner();
    }

    /**
     * 获取Banner列表
     * @return array
     */
    public function getPage()
    {
         $field = 'id,site_id,image,sort,create_at,update_at';
        $order = 'sort desc';
        $search_model = $this->model->where([['site_id','=',$this->site_id]])->withSearch(['site_id', 'image'])
            ->field($field)
            ->order($order);
         
         // 需要单独处理图片 将字符串转换为数组
         $list = $search_model->select()->toArray();
        //  foreach ($list as $key => $value) {
        //     $list[$key]['image'] = explode(',', $value['image']);
        //  }
         return $list;
    }
}
