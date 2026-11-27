<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\listener\poster;

final class LeadPosterType
{
    public function handle($data = null): array
    {
        return [
            ['type' => 'hsx_lead_promotion', 'addon' => 'hsx_lead', 'name' => '商户协作推广海报', 'desc' => '本人推广邀请；扫码后由用户确认邀请关系', 'icon' => 'addon/hsx_lead/diy/tabbar/stores.png'],
            ['type' => 'hsx_lead_entity', 'addon' => 'hsx_lead', 'name' => '门店成员邀请海报', 'desc' => '仅门店负责人生成；扫码申请加入，需负责人确认', 'icon' => 'addon/hsx_lead/diy/tabbar/stores.png'],
        ];
    }
}
