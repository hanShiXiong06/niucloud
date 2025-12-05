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

namespace addon\ai_image\app\service\admin\aiimagemodel;

use addon\ai_image\app\dict\model\ModelDict;
use addon\ai_image\app\model\aiimagemodel\AiimageModel;

use core\base\BaseAdminService;


/**
 * 智能体服务层
 * Class AiimageModelService
 * @package addon\ai_image\app\service\admin\aiimagemodel
 */
class AiimageModelService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new AiimageModel();
    }

    public function asyncModel()
    {
        $base_list = ModelDict::getModelDict();
        foreach ($base_list as $v) {
            $info = $this->model->where([['key', '=', $v['key']], ['site_id', '=', $this->site_id]])->findOrEmpty();
            if ($info->isEmpty()) {
                $v['site_id'] = $this->site_id;
                $this->model->create($v);
            }
        }
        return true;
    }

    /**
     * 获取智能体列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,name,logo,desc,prompt,sort,demo_image,status,point,is_vip,create_time,is_upload_image,is_prompt,limit_image,model';
        $order = 'sort desc';

        $search_model = $this->model->where([['site_id', "=", $this->site_id]])->withSearch(["name", "status", "is_vip"], $where)->field($field)->order($order);
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
        $field = 'id,site_id,name,logo,desc,prompt,sort,demo_image,status,point,is_vip,create_time,is_upload_image,is_prompt,limit_image,model';

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
