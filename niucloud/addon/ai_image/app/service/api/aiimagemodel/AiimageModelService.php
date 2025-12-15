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

namespace addon\ai_image\app\service\api\aiimagemodel;

use addon\ai_image\app\model\aiimagemodel\AiimageModel;

use core\base\BaseApiService;


/**
 * 智能体服务层
 * Class AiimageModelService
 * @package addon\ai_image\app\service\admin\aiimagemodel
 */
class AiimageModelService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new AiimageModel();
    }

    /**
     * 获取智能体列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,name,logo,desc,prompt,sort,demo_image,status,point,is_vip,create_time,is_upload_image,is_prompt';
        $order = 'sort desc';

        $search_model = $this->model->where([['site_id', "=", $this->site_id]])
            ->where(['status' => 1])
            ->withSearch(["name", "is_vip"], $where)->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取智能体信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,name,logo,desc,prompt,sort,demo_image,status,point,is_vip,create_time,is_upload_image,is_prompt,limit_image';

        $info = $this->model->field($field)->where([['id', "=", $id]])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 添加智能体
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $res = $this->model->create($data);
        return $res->id;

    }

    /**
     * 智能体编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {

        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 删除智能体
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        $res = $model->delete();
        return $res;
    }

}
