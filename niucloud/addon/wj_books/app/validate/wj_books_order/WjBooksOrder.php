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

namespace addon\wj_books\app\validate\wj_books_order;
use core\base\BaseValidate;
/**
 * 订单验证器
 * Class WjBooksOrder
 * @package addon\wj_books\app\validate\wj_books_order
 */
class WjBooksOrder extends BaseValidate
{
    protected $rule = [
        'status' => 'between:1,5',
        'audit_progress' => 'between:0,100',
        'final_price' => 'egt:0',
        'accepted_quantity' => 'egt:0',
        'rejected_quantity' => 'egt:1',
        'reject_reason' => 'require',
        'express_channel_id' => 'require',
        'express_channel' => 'require',
        'express_waybill' => 'require',
        'cancel_reason' => 'require',
    ];

    protected $message = [
        'status.between' => ['common_validate.between', ['status', '1', '5']],
        'audit_progress.between' => ['common_validate.between', ['audit_progress', '0', '100']],
        'final_price.egt' => ['common_validate.egt', ['final_price', '0']],
        'accepted_quantity.egt' => ['common_validate.egt', ['accepted_quantity', '0']],
        'rejected_quantity.egt' => ['common_validate.egt', ['rejected_quantity', '1']],
        'reject_reason.require' => ['common_validate.require', ['reject_reason']],
        'express_channel_id.require' => ['common_validate.require', ['express_channel_id']],
        'express_channel.require' => ['common_validate.require', ['express_channel']],
        'express_waybill.require' => ['common_validate.require', ['express_waybill']],
        'cancel_reason.require' => ['common_validate.require', ['cancel_reason']],
    ];

    protected $scene = [
        'update_status' => ['status'],
        'update_audit_progress' => ['audit_progress'],
        'update_book_price' => ['final_price', 'accepted_quantity'],
        'add_rejected_book' => ['rejected_quantity', 'reject_reason'],
        'update_express' => ['express_channel_id', 'express_channel', 'express_waybill'],
        'cancel_order' => ['cancel_reason'],
    ];
} 