<?php
namespace addon\tk_jhkd\app\service\admin\agreement;


use addon\tk_jhkd\app\dict\agreement\AgreementDict;
use addon\tk_jhkd\app\service\core\CoreAgreementService;
use core\base\BaseAdminService;

/**
 * 协议服务类
 * Class AgreementService
 * @package app\service\admin\sys
 */
class AgreementService extends BaseAdminService
{

    /**
     * 协议列表（不分页）
     * @return array
     */
    public function getList()
    {
        $type = AgreementDict::getType();
        $list = [];
        foreach ($type as $k => $v)
        {
            $list[$k] = $this->getAgreement($k);
            $list[$k]['type_name'] = $v;
        }
        return $list;
    }
    /**
     * 获取协议内容
     * @param string $key
     * @return array
     */
    public function getAgreement(string $key)
    {
        return (new CoreAgreementService())->getAgreement($this->site_id, $key);
    }

    /**
     * 设置协议
     * @param string $key
     * @param string $title
     * @param string $content
     * @return bool
     */
    public function setAgreement(string $key, string $title, string $content)
    {
        return (new CoreAgreementService())->setAgreement($this->site_id, $key, $title, $content);
    }
}