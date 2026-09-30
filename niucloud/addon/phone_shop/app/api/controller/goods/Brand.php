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

namespace addon\phone_shop\app\api\controller\goods;

use core\base\BaseApiController;
use addon\phone_shop\app\service\admin\goods\BrandService;


/**
 * 商品品牌控制器
 * @description 商品品牌
 * Class Brand
 * @package addon\phone_shop\app\adminapi\controller\goods
 */
class Brand extends BaseApiController
{
   /**
    * 获取商品品牌分页列表
    * @description 查看列表-分页
    * @return \think\Response
    */
    public function pages(){
        $data = $this->request->params([
             [ "brand_name","" ],
             [ 'order', '' ],
             [ 'sort', '' ]
        ]);
        return success((new BrandService())->getPage($data));
    }

    /**
     * 获取商品品牌列表
     * @description 查看商品列表-全部
     * @return \think\Response
     */
    public function lists(){
        $data = $this->request->params([
            ["brand_name",""]
        ]);
        return success((new BrandService())->getList($data));
    }

    /**
     * 商品品牌详情
     * @description 查看详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new BrandService())->getInfo($id));
    }

    

}
