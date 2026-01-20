<?php
namespace addon\tk_jhkd\app\service\api;
use addon\tk_jhkd\app\service\core\CoreAgreementService;
use core\base\BaseApiService;

/**
 * 协议服务类
 */
class AgreementService extends BaseApiService
{
    /**
     * 获取协议内容
     * @param string $key
     * @return array
     */
    public function getAgreement(string $key)
    {
        return ( new CoreAgreementService() )->getAgreement($this->site_id, $key);
    }

}