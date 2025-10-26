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

namespace addon\home_service\app\service\core\help_feedback;

use addon\home_service\app\dict\help_feedback\HelpDict;
use addon\home_service\app\model\help_feedback\Help;
use addon\home_service\app\model\help_feedback\HelpCategory;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 帮助服务层
 * Class CoreHelpService
 * @package addon\o2o\app\service\api\help_feedback
 */
class CoreHelpService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Help();
    }

    /**
     * 获取帮助列表
     * @param $data
     */
    public function getHelpPage($where)
    {
        $category_list = (new HelpCategory())->where([['site_id', '=', $this->site_id], ['is_show', '=', HelpDict::YES]])->order('sort desc')->column('category_name,is_default', 'category_id');
        $field = 'help_id,site_id,category_id,name';
        $order = 'sort desc';
        $help_list = $this->model->where([['site_id', '=', $this->site_id], ['is_show', '=', HelpDict::YES]])->withSearch(["name", 'category_id', 'type'], $where)->field($field)->order($order)->select()->toArray();
        $result = [];
        foreach ($category_list as $cid => $tarm) {
            $result[] = [
                'category_id' => $cid,
                'is_default' => $tarm['is_default'],
                'category_name' => $tarm['category_name'],
                'help_list' => array_values(array_filter($help_list, function ($help) use ($cid) {
                    return $help['category_id'] == $cid;
                }))
            ];
        }

        return $result;
    }

    /**
     * 获取帮助详情
     * @param $data
     */
    public function getHelpInfo($where)
    {
        $field = 'help_id,site_id,category_id,name,content,views_count';
        $order = 'sort desc';
        $help_info = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['type', '=', $where['type']],
            ['is_show', '=', HelpDict::YES],
            ['help_id', '=', $where['help_id']]
        ])->field($field)->order($order)->findOrEmpty();

        if ($help_info->isEmpty()) throw new CommonException('HELP_NOT_EXIST');

        $help_info->views_count += 1;
        $help_info->save();

        return $help_info;
    }


}
