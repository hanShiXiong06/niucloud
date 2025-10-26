<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\service\admin\help_feedback;

use addon\home_service\app\model\goods\GoodsCategory;
use addon\home_service\app\model\help_feedback\Help;
use addon\home_service\app\model\help_feedback\HelpCategory;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;


/**
 *  帮助服务层
 * Class HelpService
 * @package app\service\admin\help_category
 */
class HelpService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Help();
    }

    /**
     * 获取帮助列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'help_id,site_id,name,type,category_id,content,sort,is_show,views_count';
        $order = 'sort desc';
        $search_model = $this->model->where([['site_id', '=', $this->site_id]])->withSearch(["name",'category_id','type'], $where)->field($field)->order($order)->append(['category_name','type_name', 'is_show_name']);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取帮助详情
     * @param $help_id
     * @return array
     */
    public function getInfo($help_id)
    {
        $field = 'help_id,site_id,name,type,category_id,content,sort,is_show,views_count';
        return $this->model->where([['site_id', '=', $this->site_id], ['help_id', '=', $help_id]])->field($field)->append(['category_name','type_name', 'is_show_name'])->findOrEmpty()->toArray();
    }


    /**
     * 添加帮助
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['create_time'] = time();

        if (!empty($data['category_id'])){
            (new HelpCategory())->where([['category_id', '=', $data['category_id']]])->inc('help_count', 1)->update();
        }
        $res = $this->model->create($data);
        return $res->help_id;

    }

    /**
     * 帮助编辑
     * @param int $help_id
     * @param array $data
     * @return bool
     */
    public function edit(int $help_id, array $data)
    {
        $data['update_time'] = time();
        $info = $this->model->where([['help_id', '=', $help_id], ['site_id', '=', $this->site_id]])->findOrEmpty();

        Db::startTrans();
        try {
            if (!empty($data['category_id']) && $info->category_id != $data['category_id']){
                (new HelpCategory())->where([['category_id', '=', $info->category_id]])->dec('help_count', 1)->update();
                (new HelpCategory())->where([['category_id', '=', $data['category_id']]])->inc('help_count', 1)->update();
            }
            $this->model->where([['help_id', '=', $help_id], ['site_id', '=', $this->site_id]])->update($data);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage() . $e->getfile() . $e->getline());
        }
    }


    /**
     * 删除帮助
     * @param int $help_id
     * @return bool
     */
    public function del($help_id)
    {
        $info = $this->model->where([['help_id', '=', $help_id], ['site_id', '=', $this->site_id]])->findOrEmpty();
        if (!empty($info->category_id)){
            (new HelpCategory())->where([['category_id', '=', $info->category_id]])->dec('help_count', 1)->update();
        }
        $res = $this->model->where([['help_id', '=', $help_id], ['site_id', '=', $this->site_id]])->delete();
        return $res;
    }

    /**
     * 查询所有数据列表
     * @param array $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getList($where)
    {
        $list = $this->model->where([['site_id', '=', $this->site_id]])->field("category_id, category_name")->withSearch(["category_name"], $where)->order("create_time desc")->select()->toArray();

        return $list;
    }

}
