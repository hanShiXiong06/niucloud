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

namespace addon\ai_image\app\service\admin\aiimagecreate;

use addon\ai_image\app\model\aiimagecreate\AiimageCreate;
use app\model\member\Member;
use addon\ai_image\app\model\aiimagemodel\AiimageModel;

use core\base\BaseAdminService;


/**
 * 作品列服务层
 * Class AiimageCreateService
 * @package addon\ai_image\app\service\admin\aiimagecreate
 */
class AiimageCreateService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new AiimageCreate();
    }

    /**
     * 获取作品列列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,member_id,model_id,prompt,image_urls,aspect_ratio,images,status,point,is_self,msg,platform,channel,create_time';
        $order = 'create_time desc';

        $search_model = $this->model->where([ [ 'site_id' ,"=", $this->site_id ] ])->withSearch(["member_id","model_id","status","create_time"], $where)->with(['member','aiimageModel'])->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取作品列信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,member_id,model_id,prompt,image_urls,aspect_ratio,images,status,point,is_self,msg,platform,channel,create_time';

        $info = $this->model->field($field)->where([['id', "=", $id]])->with(['member','aiimageModel'])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 添加作品列
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
     * 作品列编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {

        $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 删除作品列
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->find();
        $res = $model->delete();
        return $res;
    }
    
    public function getMemberAll(){
       $memberModel = new Member();
       return $memberModel->where([["site_id","=",$this->site_id]])->select()->toArray();
    }

    public function getAiimageModelAll(){
       $aiimageModelModel = new AiimageModel();
       return $aiimageModelModel->where([["site_id","=",$this->site_id]])->select()->toArray();
    }

}
