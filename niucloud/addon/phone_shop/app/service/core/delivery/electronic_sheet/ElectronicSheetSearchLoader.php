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

namespace addon\phone_shop\app\service\core\delivery\electronic_sheet;

use addon\phone_shop\app\service\core\delivery\CoreConfigService;
use core\exception\AdminException;
use core\loader\Loader;

/**
 * Class ElectronicSheetSearchLoader
 * @package addon\phone_shop\app\service\core\delivery\electronic_sheet
 * @method  string|null electronicSheet(array $data) 电子面单
 */
class ElectronicSheetSearchLoader extends Loader
{

    public array $method = [
        'kdbird' => 'KdniaoSearch',
   //需线上测试,先隐藏     'kd100' => 'Kd100Search',
    ];

    public function __construct($site_id)
    {
        $config = ( new CoreConfigService() )->getDeliveryElectronSheeticConfig($site_id);
        if (!empty($config['interface_type']) && $config['interface_type'] !== 'kdbird') {
            throw new AdminException('本站使用扩展物流服务，请在发货窗口先申请面单；原单补打请打开物流任务，不使用快递鸟模板');
        }
        if(empty($config['interface_type']) || !isset($this->method[$config['interface_type']]) ){
            throw new AdminException('NOT_CONFIGURED_DELIVERY_TYPE');
        }
        parent::__construct($this->method[$config['interface_type']], $config);
    }

    /**
     * 空间名
     * @var string
     */
    protected $namespace = '\\addon\\phone_shop\\app\\service\\core\\delivery\\electronic_sheet\\';

    protected $config_name = 'electronic_sheet';

    /**
     * 默认驱动
     * @return mixed
     */
    protected function getDefault()
    {
        return 'kdbird';
    }
}
