<?php
namespace addon\fast_pay\app\dict\config;
class ConfigDict{
    public static function getConfigType()
    {
        return 'FAST_PAY_CONFIG_TYPE';
    }
    public static function getPartnerConfigType()
    {
        return 'FAST_PAY_PARTNER_CONFIG_TYPE';
    }
    public static function getBusinessConfigType()
    {
        return 'FAST_PAY_BUSINESS_CONFIG_TYPE';
    }
}
