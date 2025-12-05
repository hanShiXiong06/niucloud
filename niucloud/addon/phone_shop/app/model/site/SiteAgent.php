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

namespace addon\phone_shop\app\model\site;

use core\base\BaseModel;

/**
 * 商户代理关系模型
 * Class SiteAgent
 * @package addon\phone_shop\app\model\site
 */
class SiteAgent extends BaseModel
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
    protected $name = 'phone_shop_site_agent';

    /**
     * 追加的字段
     * @var array
     */
    protected $append = ['site_name'];

    /**
     * 关联被代理的站点
     */
    public function agentSite()
    {
        return $this->belongsTo(Site::class, 'agent_site_id', 'site_id')
            ->field('site_id,site_name');
    }

    /**
     * 获取器 - 站点名称
     */
    public function getSiteNameAttr($value, $data)
    {
        if (isset($this->agentSite)) {
            return $this->agentSite->site_name;
        }
        // 如果关联数据不存在，则直接查询
        $site = (new Site())->where([['site_id', '=', $data['agent_site_id']]])
            ->field('site_name')
            ->find();
        return $site ? $site['site_name'] : '';
    }
} 