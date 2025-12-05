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

namespace addon\phone_shop\app\adminapi\controller\site;

use core\base\BaseAdminController;
use addon\phone_shop\app\service\admin\site\SiteAgentService;

/**
 * 商户代理关系控制器
 * Class SiteAgent
 * @package addon\phone_shop\app\adminapi\controller\site
 */
class SiteAgent extends BaseAdminController
{
    /**
     * 获取代理关系列表
     * @return mixed
     */
    public function lists()
    {
        $data = $this->request->params([
            ['agent_site_id', ''],
            ['status', '1'],
        ]);
        return success((new SiteAgentService())->getPage($data));
    }

    /**
     * 获取可代理的站点列表
     * @return mixed
     */
    public function getAvailableSites()
    {
        return success((new SiteAgentService())->getAvailableSites());
    }

    /**
     * 添加代理关系
     * @return mixed
     */
    public function add()
    {
        $data = $this->request->params([
            ['agent_site_id', 0],
        ]);
        
        $this->validate($data, [
            'agent_site_id' => 'require|integer|gt:0'
        ]);
        
        $res = (new SiteAgentService())->add($data);
        return success('ADD_SUCCESS', ['id' => $res]);
    }

    /**
     * 修改代理状态
     * @return mixed
     */
    public function modifyStatus()
    {
        $data = $this->request->params([
            ['id', 0],
            ['status', 0],
        ]);
        
        $this->validate($data, [
            'id' => 'require|integer|gt:0',
            'status' => 'require|in:0,1'
        ]);
        
        return (new SiteAgentService())->modifyStatus($data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 删除代理关系
     * @param int $id
     * @return mixed
     */
    public function del(int $id)
    {
        (new SiteAgentService())->del($id);
        return success('DELETE_SUCCESS');
    }
}