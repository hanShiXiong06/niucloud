<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\dict\order;

/**
 * 发票相关字典类
 * Class InvoiceDict
 */
class InvoiceDict
{
    const ELECTRON_REGULAR_INVOICE = 'electron_regular_invoice';  // 电子普通发票
    const ELECTRON_VAT_INVOICE = 'electron_vat_invoice';  // 电子增值税专用发票

    const SERVICE_DETAILS = 'service_details';//服务明细

    const INDIVIDUAL = 'individual';//个人
    const ENTERPRISE = 'enterprise';//企业

    const WAIT = '0';//待开具
    const ISSUED = '1';//已开具

    /**
     * 发票类型
     * @param $type
     * @return array|mixed|string
     */
    public static function getType($type = '')
    {
        $data = [
            self::ELECTRON_REGULAR_INVOICE => get_lang('dict_home_service_invoice_type.electron_regular_invoice'),
            self::ELECTRON_VAT_INVOICE => get_lang('dict_home_service_invoice_type.electron_vat_invoice'),
        ];
        if (!$type) {
            return $data;
        }
        return $data[ $type ] ?? '';
    }

    /**
     * 发票内容
     * @param $type
     * @return array|mixed|string
     */
    public static function getContent($type = '')
    {
        $data = [
            self::SERVICE_DETAILS => get_lang('dict_home_service_invoice_content.service_details'),
        ];
        if (!$type) {
            return $data;
        }
        return $data[ $type ] ?? '';
    }

    /**
     * 抬头类型
     * @param $type
     * @return array|mixed|string
     */
    public static function getHeaderType($type = '')
    {
        $data = [
            self::INDIVIDUAL => get_lang('dict_home_service_invoice_header_type.individual'),
            self::ENTERPRISE => get_lang('dict_home_service_invoice_header_type.enterprise'),
        ];
        if (!$type) {
            return $data;
        }
        return $data[ $type ] ?? '';
    }

    /**
     * 发票状态
     * @param $type
     * @return array|mixed|string
     */
    public static function getStatus($type = '')
    {
        $data = [
            self::WAIT => get_lang('dict_home_service_invoice_status.wait'),
            self::ISSUED => get_lang('dict_home_service_invoice_status.issued'),
        ];
        if (!$type) {
            return $data;
        }
        return $data[ $type ] ?? '';
    }



}
