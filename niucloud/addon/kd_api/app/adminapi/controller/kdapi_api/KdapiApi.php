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

namespace addon\kd_api\app\adminapi\controller\kdapi_api;

use addon\kd_api\app\dict\status\StatusDict;
use app\service\admin\member\MemberService;
use core\base\BaseAdminController;
use addon\kd_api\app\service\admin\kdapi_api\KdapiApiService;


/**
 * api对接控制器
 * Class KdapiApi
 * @package addon\kd_api\app\adminapi\controller\kdapi_api
 */
class KdapiApi extends BaseAdminController
{
    public function getStatus()
    {
        return success(StatusDict::getStatusDict());
    }
   /**
    * 获取api对接列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["member_id",""],
             ["api_key",""],
             ["status",""]
        ]);
        return success((new KdapiApiService())->getPage($data));
    }

    /**
     * api对接详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new KdapiApiService())->getInfo($id));
    }

    /**
     * 添加api对接
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
             ["member_id",0],
             ["rate",0.00],
             ["api_key",""],
             ["api_secret",""],
             ["status",0],
             ["qps",0],
             ["limit",0],
             ["num",0],
             ["commission",0.00],

        ]);
        $this->validate($data, 'addon\kd_api\app\validate\kdapi_api\KdapiApi.add');
        $id = (new KdapiApiService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * api对接编辑
     * @param $id  api对接id
     * @return \think\Response
     */
    public function edit(int $id){
        $data = $this->request->params([
             ["member_id",0],
             ["rate",0.00],
             ["status",0],
             ["qps",0],
             ["limit",0],
             ["num",0],
             ["commission",0.00],
        ]);
        $this->validate($data, 'addon\kd_api\app\validate\kdapi_api\KdapiApi.edit');
        (new KdapiApiService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * api对接删除
     * @param $id  api对接id
     * @return \think\Response
     */
    public function del(int $id){
        (new KdapiApiService())->del($id);
        return success('DELETE_SUCCESS');
    }

    
    public function getMemberAll(){
        $data = $this->request->params([
            ['keyword', ''],
            ['register_type', ''],
            ['register_channel', ''],
            ['create_time', []],
            ['member_label', 0],
            ['member_level', 0],
        ]);
        return success((new MemberService())->getPage($data));
    }

}
