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

namespace addon\home_service\app\model\help_feedback;

use addon\home_service\app\dict\help_feedback\FeedbackDict;
use addon\home_service\app\model\Member;
use addon\home_service\app\model\store\Store;
use addon\home_service\app\model\technician\Technician;
use core\base\BaseModel;

/**
 * 反馈模型
 */
class Feedback extends BaseModel
{

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'feedback_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'home_service_feedback';

    /**
     * 搜索器:标题
     * @param $value
     * @param $data
     */
    public function searchTitleAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where("title", 'like', '%' . $this->handelSpecialCharacter($value) . '%');
        }
    }

    /**
     * 搜索器:来源
     * @param $value
     * @param $data
     */
    public function searchSourceAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where("source", '=', $value);
        }
    }

    public function getSourceNameAttr($value, $data)
    {
        if (isset($data[ 'source' ])) {
            return FeedbackDict::getSource()[$data['source']] ?? '';
        }
    }

    public function getRelatedNameAttr($value, $data)
    {
        $name = '';
        if (isset($data[ 'related_id' ])) {
            switch ($data['source']){
                case FeedbackDict::MEMBER:
                    $name = (new Member())->where([['member_id', '=', $data['related_id']]])->value('nickname');
                    break;
                case FeedbackDict::STORE:
                    $name = (new Store())->where([['store_id', '=', $data['related_id']]])->value('store_name');
                    break;
                case FeedbackDict::TECHNICIAN:
                    $name = (new Technician())->where([['id', '=', $data['related_id']]])->value('real_name');
                    break;
            }
        }
         return $name;
    }
}
