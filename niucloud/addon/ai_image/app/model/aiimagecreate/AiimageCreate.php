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

namespace addon\ai_image\app\model\aiimagecreate;

use core\base\BaseModel;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

use app\model\member\Member;

use addon\ai_image\app\model\aiimagemodel\AiimageModel;

/**
 * 作品列模型
 * Class AiimageCreate
 * @package addon\ai_image\app\model\aiimagecreate
 */
class AiimageCreate extends BaseModel
{

    

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'aiimage_create';

    // 设置json类型字段
    protected $json = ['image_urls'];

    // 设置JSON数据返回数组
    protected $jsonAssoc = true;


    /**
     * 搜索器:作品列会员
     * @param $value
     * @param $data
     */
    public function searchMemberIdAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("member_id", $value);
        }
    }
    
    /**
     * 搜索器:作品列模型
     * @param $value
     * @param $data
     */
    public function searchModelIdAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("model_id", $value);
        }
    }
    
    /**
     * 搜索器:作品列状态
     * @param $value
     * @param $data
     */
    public function searchStatusAttr($query, $value, $data)
    {
       if ($value!='') {
            $query->where("status", $value);
        }
    }
    
    /**
     * 搜索器:作品列创建时间
     * @param $value
     * @param $data
     */
    public function searchCreateTimeAttr($query, $value, $data)
    {
        $start = empty($value[0]) ? 0 : strtotime($value[0]);
        $end = empty($value[1]) ? 0 : strtotime($value[1]);
        if ($start > 0 && $end > 0) {
             $query->where([["create_time", "between", [$start, $end]]]);
        } else if ($start > 0 && $end == 0) {
            $query->where([["create_time", ">=", $start]]);
        } else if ($start == 0 && $end > 0) {
            $query->where([["create_time", "<=", $end]]);
        }
    }
    
    

    

    
    public function member(){
       return $this->hasOne(Member::class, 'member_id', 'member_id')->joinType('left')->withField('nickname,member_id')->bind(['member_id_name'=>'nickname']);
    }

    public function aiimageModel(){
       return $this->hasOne(AiimageModel::class, 'id', 'model_id')->joinType('left')->withField('name,id')->bind(['model_id_name'=>'name']);
    }

}
