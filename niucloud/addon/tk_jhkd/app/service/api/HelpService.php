<?php
namespace addon\tk_jhkd\app\service\api;

use addon\tk_jhkd\app\model\help\Help;
use core\base\BaseApiService;

/**
 * 帮助中心
 */
class HelpService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Help();
    }
    /**
     * 获取帮助中心列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,title,content';
        $order = '';
        $search_model = $this->model->where([['site_id', '=', $this->site_id]])->withSearch([], $where)->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }
}