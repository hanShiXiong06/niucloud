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

namespace addon\home_service\app\api\controller\store;

use addon\home_service\app\service\api\store\EvaluateService;
use core\base\BaseApiController;


/**
 * 评价控制器
 * Class Evaluate
 * @package addon\home_service\app\api\controller\store
 */
class Evaluate extends BaseApiController
{
    /**
     * 获取商品评价列表
     * @return \think\Response
     */
    public function pages()
    {
        $data = $this->request->params([
            [ 'order', '' ],
            [ 'sort', 'desc' ],
        ]);
        return success(( new EvaluateService() )->getPage($data));
    }


}
