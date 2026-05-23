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

namespace addon\hsx_phone_query\app\adminapi\controller\hsx_phone_query_config;

use core\base\BaseAdminController;
use addon\hsx_phone_query\app\service\admin\hsx_phone_query_config\HsxPhoneQueryConfigService;


/**
 * 配置信息控制器
 * Class HsxPhoneQueryConfig
 * @package addon\hsx_phone_query\app\adminapi\controller\hsx_phone_query_config
 */
class HsxPhoneQueryConfig extends BaseAdminController
{
   /**
    * 获取配置信息列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([

        ]);
        return success((new HsxPhoneQueryConfigService())->getPage($data));
    }

    /**
     * 获取服务商配置
     */
    public function config()
    {
        return success((new HsxPhoneQueryConfigService())->getProviderConfig());
    }

    /**
     * 保存服务商配置
     */
    public function saveConfig()
    {
        (new HsxPhoneQueryConfigService())->saveProviderConfig($this->request->post());
        return success('EDIT_SUCCESS');
    }

    /**
     * 配置信息详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new HsxPhoneQueryConfigService())->getInfo($id));
    }

    /**
     * 添加配置信息
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
             ["appid",""],
             ["Secret",""]
        ]);
        $this->validate($data, 'addon\hsx_phone_query\app\validate\hsx_phone_query_config\HsxPhoneQueryConfig.add');
        $id = (new HsxPhoneQueryConfigService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 配置信息编辑
     * @param $id  配置信息id
     * @return \think\Response
     */
    public function edit(int $id){
        $data = $this->request->params([
             ["appid",""],
             ["Secret",""]
        ]);
        $this->validate($data, 'addon\hsx_phone_query\app\validate\hsx_phone_query_config\HsxPhoneQueryConfig.edit');
        (new HsxPhoneQueryConfigService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 配置信息删除
     * @param $id  配置信息id
     * @return \think\Response
     */
    public function del(int $id){
        (new HsxPhoneQueryConfigService())->del($id);
        return success('DELETE_SUCCESS');
    }

    
}
