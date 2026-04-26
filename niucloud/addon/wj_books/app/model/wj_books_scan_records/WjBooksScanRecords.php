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

namespace addon\wj_books\app\model\wj_books_scan_records;

use core\base\BaseModel;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

use app\model\member\Member;

/**
 * 图书扫描记录模型
 * Class WjBooksScanRecords
 * @package addon\wj_books\app\model\wj_books_scan_records
 */
class WjBooksScanRecords extends BaseModel
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
    protected $name = 'wj_books_scan_records';

    /**
     * 自动写入时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = false;

    /**
     * 搜索器:图书扫描记录用户编码
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
     * 搜索器:图书扫描记录ISBN码
     * @param $value
     * @param $data
     */
    public function searchIsbnAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("isbn", $value);
        }
    }
    
    

    

    
    public function member(){
       return $this->hasOne(Member::class, 'member_id', 'member_id')->joinType('left')->withField('member_no,member_id')->bind(['member_id_name'=>'member_no']);
    }

}
