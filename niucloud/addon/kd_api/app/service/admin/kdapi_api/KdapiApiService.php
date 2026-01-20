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

namespace addon\kd_api\app\service\admin\kdapi_api;

use addon\kd_api\app\dict\status\StatusDict;
use addon\kd_api\app\model\kdapi_api\KdapiApi;
use addon\kd_api\app\service\core\common\CommonService;
use app\model\member\Member;

use core\base\BaseAdminService;
use core\exception\CommonException;


/**
 * api对接服务层
 * Class KdapiApiService
 * @package addon\kd_api\app\service\admin\kdapi_api
 */
class KdapiApiService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new KdapiApi();
    }

    /**
     * 获取api对接列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,member_id,rate,api_key,api_secret,status,qps,limit,num,commission,create_time';
        $order = 'create_time desc';

        $search_model = $this->model->where([['site_id', "=", $this->site_id]])->withSearch(["member_id", "api_key", "status"], $where)->with(['member'])->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        foreach ($list['data'] as $k=>$v) {
            $list['data'][$k]['status_name'] = StatusDict::getStatusDict($v['status']);
        }
        return $list;
    }

    /**
     * 获取api对接信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,member_id,rate,api_key,api_secret,status,qps,limit,num,commission,create_time,callback_url';

        $info = $this->model->field($field)->where([['id', "=", $id]])->with(['member'])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 添加api对接
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        if ($data['member_id'] < 1) throw new CommonException('请绑定正确会员');
        $info=$this->model->where([['member_id', '=', $data['member_id']], ['site_id', '=', $this->site_id]])->findOrEmpty();
        if (!$info->isEmpty()) throw new CommonException('会员已绑定api对接,请勿重复绑定');
        $commonService = new CommonService();
        $data['api_key'] = $commonService->createKey();
        $data['api_secret'] = $commonService->createSecret();
        $res = $this->model->create($data);
        return $res->id;

    }

    /**
     * api对接编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        $info=$this->model->where([['member_id', '=', $data['member_id']], ['site_id', '=', $this->site_id]])->findOrEmpty();
        if (!$info->isEmpty()&&$info['id']!=$id) throw new CommonException('会员已绑定api对接');
        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 删除api对接
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        $res = $model->delete();
        return $res;
    }

    public function getMemberAll()
    {
        $memberModel = new Member();
        return $memberModel->where([["site_id", "=", $this->site_id]])->select()->toArray();
    }

}
