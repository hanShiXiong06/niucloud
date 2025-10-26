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

namespace addon\home_service\app\model\order;

use addon\home_service\app\dict\order\InvoiceDict;
use addon\home_service\app\model\Member;
use core\base\BaseModel;
use think\db\Query;

/**
 * 发票模型
 * Class Invoice
 * @package addon\home_service\app\model\invoice
 */
class Invoice extends BaseModel
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
    protected $name = 'home_service_invoice';

    // 设置json类型字段
    protected $json = ['order_ids'];

    // 设置JSON数据返回数组
    protected $jsonAssoc = true;

    /**
     * 状态搜索器
     * @param $value
     * @param $data
     */
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value != 'all') {
            $query->where("status", '=', $value);
        }
    }

    /**
     * 抬头类型搜索器
     * @param $value
     * @param $data
     */
    public function searchHeaderTypeAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("header_type", '=', $value);
        }
    }

    /**
     * 发票类型搜索器
     * @param $value
     * @param $data
     */
    public function searchTypeAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("type", '=', $value);
        }
    }

    /**
     * 发票开票状态搜索器
     * @param $value
     * @param $data
     */
    public function searchIsIssueInvoiceAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("is_issue_invoice", '=', $value);
        }
    }



    /**
     * 发票类型名称
     * @param $value
     * @return mixed
     */
    public function getTypeNameAttr($value, $data)
    {
        if (isset($data['type'])) {
            return InvoiceDict::getType()[$data['type']] ?? '';
        }
    }

    /**
     * 发票类型名称
     * @param $value
     * @return mixed
     */
    public function getHeaderTypeNameAttr($value, $data)
    {
        if (isset($data['header_type'])) {
            return InvoiceDict::getHeaderType()[$data['header_type']] ?? '';
        }
    }

    /**
     * 发票类型名称
     * @param $value
     * @return mixed
     */
    public function getContentNameAttr($value, $data)
    {
        if (isset($data['content'])) {
            return InvoiceDict::getContent()[$data['content']] ?? '';
        }
    }

    /**
     * 发票状态名称
     * @param $value
     * @return mixed
     */
    public function getStatusNameAttr($value, $data)
    {
        if (isset($data['status'])) {
            return InvoiceDict::getStatus()[$data['status']] ?? '';
        }
    }

    public function member(){
        return $this->hasOne(Member::class, 'member_id', 'member_id');
    }

    /**
     * 搜索器:订单id
     * @param $value
     * @param $data
     */
    public function searchOrderIdAttr(Query $query, $value, $data)
    {
        if ($value) {
            if (is_array($value)) {
                $temp_where = array_map(function ($item) {
                    return '%"' . $item . '"%';
                }, $value);
            } else {
                $temp_where = [ '%"' . $value . '"%' ];
            }
            $query->where('order_ids', 'like', $temp_where, 'or');
        }
    }

}
