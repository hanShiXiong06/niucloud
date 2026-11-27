<?php
declare(strict_types=1);

namespace addon\phone_shop\app\model\order;

use core\base\BaseModel;

class OrderOfflineRecord extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'phone_shop_order_offline_record';
    protected $type = ['voucher_urls' => 'json'];

    public function getStatusNameAttr($value, array $data): string
    {
        return [
            'pending' => '待联系',
            'contacted' => '已联系待到店',
            'voucher_submitted' => '付款凭证待审核',
            'voucher_rejected' => '付款凭证未通过',
            'paid' => '已收款待交付',
            'credit' => '已挂账待交付',
            'delivered' => '已完成交付',
            'closed' => '已关闭',
        ][(string)($data['status'] ?? '')] ?? '待处理';
    }
}
