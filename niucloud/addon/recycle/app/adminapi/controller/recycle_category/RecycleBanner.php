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

namespace addon\recycle\app\adminapi\controller\recycle_category;

use addon\recycle\app\service\admin\recycle_category\RecycleBannerService;
use core\base\BaseAdminController;
use think\Response;

/**
 * Banner管理控制器
 * Class RecycleBanner
 * @package addon\recycle\app\adminapi\controller\recycle_category
 */
class RecycleBanner extends BaseAdminController
{

    /**
     * 获取Banner列表
     * @return Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['image', '']
        ]);
        return success((new RecycleBannerService())->getPage($data));
    }

    /**
     * 获取Banner信息
     * @param int $id
     * @return Response
     */
    public function info(int $id)
    {
        return success((new RecycleBannerService())->getInfo($id));
    }

    /**
     * 添加Banner
     * @return Response
     */
    public function add()
    {
        $data = $this->request->params([
            ['image', '']
        ]);
        
        $res = (new RecycleBannerService())->add($data);
        return success('ADD_SUCCESS', $res);
    }

    /**
     * 编辑Banner
     * @param int $id
     * @return Response
     */
    public function edit(int $id)
    {
        $data = $this->request->params([
            ['image', '', 'require']
        ]);
        $data['id'] = $id;
        (new RecycleBannerService())->edit($data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 删除Banner
     * @param int $id
     * @return Response
     */
    public function del(int $id)
    {
       
        return success( (new RecycleBannerService())->delete($id));
    }

    // 更改排序
    public function changeSort(int $id, int $sort)
    {
        (new RecycleBannerService())->changeSort($id, $sort);
        return success('CHANGE_SORT_SUCCESS');
    }
}
