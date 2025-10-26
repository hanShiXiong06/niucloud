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

use addon\home_service\app\model\help_feedback\Help;
use addon\home_service\app\model\help_feedback\HelpCategory;
use core\base\BaseAdminService;
use core\exception\AdminException;


/**
 *  帮助分类服务层
 * Class HelpCategoryService
 * @package app\service\admin\help_category
 */
class HelpCategoryService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new HelpCategory();
    }

    /**
     * 获取帮助分类列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'category_id,site_id,category_name,is_show,help_count,sort,is_builtin_data';
        $order = 'sort desc';
        $search_model = $this->model->where([['site_id', '=', $this->site_id]])->withSearch(["category_name"], $where)->field($field)->order($order)->append(['is_show_name']);
        $list = $this->pageQuery($search_model);
        return $list;
    }


    /**
     * 添加帮助分类
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['create_time'] = time();
        if ($data['is_default'] == 1){
            $this->model->where([['site_id', '=', $this->site_id]])->update(['is_default' => 0]);
        }
        $res = $this->model->create($data);
        return $res->category_id;

    }

    /**
     * 帮助分类编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        $data['update_time'] = time();
        if ($data['is_default'] == 1){
            $this->model->where([['site_id', '=', $this->site_id]])->update(['is_default' => 0]);
        }else{
            $info = $this->model->where([['category_id', '=', $id], ['site_id', '=', $this->site_id]])->findOrEmpty();
            if ($info->is_default == 1) throw new AdminException('MUST_HAVE_DEFAULT_CATEGORY');
        }
        $this->model->where([['category_id', '=', $id], ['site_id', '=', $this->site_id]])->update($data);
        return true;
    }


    /**
     * 删除帮助分类
     * @param int $category_id
     * @return bool
     */
    public function del($category_id)
    {
        $use_num = (new Help())->getCountByCategoryID($category_id);
        if ($use_num > 0) {
            throw new AdminException('HELP_CATEGORY_BE_USED');
        }
        $info = $this->model->where([['category_id', '=', $category_id], ['site_id', '=', $this->site_id]])->findOrEmpty();
        if (!$info->isEmpty() && $info->is_builtin_data == 1) throw new AdminException('BUILTIN_DATA_NOT_DEL');

        $res = $this->model->where([['category_id', '=', $category_id], ['site_id', '=', $this->site_id]])->delete();
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
