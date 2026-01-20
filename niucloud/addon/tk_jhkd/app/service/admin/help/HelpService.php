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

namespace addon\tk_jhkd\app\service\admin\help;

use addon\tk_jhkd\app\model\help\Help;
use addon\tk_jhkd\app\dict\help\HelpDict;
use core\base\BaseAdminService;


/**
 * 帮助中心服务层
 * Class HelpService
 * @package addon\tk_jhkd\app\service\admin\help
 */
class HelpService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Help();
    }

    /**
     * 同步初始帮助
     */
    public function asyncHelp()
    {
        $this->model->where(['site_id' => $this->site_id])->select()->delete();
        $startInfo = HelpDict::getHelp();
        $info=[];
        foreach ($startInfo as $k => $v) {
            $v['site_id'] = $this->site_id;
            $info[$k]=$v;
        }
        $this->model->saveAll($info);
        return true;
    }

    /**
     * 获取帮助中心列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,title,content,create_time';
        $order = 'create_time desc';
        $search_model = $this->model->where([['site_id', "=", $this->site_id], ['title', "like", '%' . $where['title'] . '%']])->withSearch(['"id","title"'], $where)->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取帮助中心信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,title,content,create_time';

        $info = $this->model->field($field)->where([['id', "=", $id]])->findOrEmpty()->toArray();;
        return $info;
    }

    /**
     * 添加帮助中心
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
     * 帮助中心编辑
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
     * 删除帮助中心
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
